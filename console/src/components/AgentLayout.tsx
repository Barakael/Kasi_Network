import { NavLink, Outlet } from 'react-router';
import { AgentDeskProvider, useAgentDesk } from '../agent/desk';
import type { User } from '../api';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';
import { NavIcon, RailStat } from './navIcons';
import { PrefBar } from './PrefBar';
import { inputClass, secondaryBtn } from './ui';

type NavItem = { to: string; kiswahili: string; english: string; hint: string; end?: boolean; icon: string; count?: (desk: ReturnType<typeof useAgentDesk>['desk']) => string | number | undefined };

const agentGroups: { label: string; items: NavItem[] }[] = [
  {
    label: '',
    items: [
      { to: '/desk', kiswahili: 'Dashibodi', english: 'Dashboard', hint: '', end: true, icon: 'live', count: (desk) => desk?.leo.kadi_count },
      { to: '/desk/wateja', kiswahili: 'Wateja', english: 'Customers', hint: '', icon: 'people', count: (desk) => desk?.hai_count },
      { to: '/desk/chapisha', kiswahili: 'Vocha', english: 'Vouchers', hint: '', icon: 'print', count: (desk) => desk?.remaining_cards },
      { to: '/desk/analytics', kiswahili: 'Ripoti', english: 'Reports', hint: '', icon: 'chart', count: (desk) => desk?.week?.kadi_count },
    ],
  },
];

const mobileTabs = agentGroups.flatMap((group) => group.items);

function agentLinkClass({ isActive }: { isActive: boolean }) {
  return `agent-nav-link ${isActive ? 'agent-nav-link-on' : ''}`;
}

function SiteSwitch({ compact = false }: { compact?: boolean }) {
  const { desk, siteId, setSiteId } = useAgentDesk();
  const { sw } = usePrefs();
  const sites = desk?.sites ?? [];
  if (sites.length < 2) {
    return null;
  }

  const select = (
    <label className={compact ? 'block' : 'mt-4 block'}>
      <span className="text-[11px] font-semibold tracking-[0.14em] text-ink-700 uppercase">{sw('Eneo', 'Site')}</span>
      <select
        className={`${inputClass} mt-1.5`}
        value={siteId ?? desk?.site.id ?? ''}
        onChange={(e) => setSiteId(Number(e.target.value))}
      >
        {sites.map((site) => (
          <option key={site.id} value={site.id}>
            {site.name}
            {site.ssid ? ` · ${site.ssid}` : ''}
          </option>
        ))}
      </select>
    </label>
  );

  return compact ? <div className="px-4 pb-3">{select}</div> : select;
}

function Brand({ user }: { user: User }) {
  const { desk } = useAgentDesk();
  const { sw } = usePrefs();
  const shop = user.tenant?.portal_name || user.tenant?.name || 'Kasi';
  const logo = user.tenant?.logo_url;

  return (
    <div className="console-aside-brand px-6 py-6">
      <div className="flex items-start gap-3">
        {logo ? (
          <img src={logo} alt="" className="h-12 w-12 rounded-2xl border border-slate-200 bg-white object-contain p-1" />
        ) : (
          <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-sm font-bold text-white">
            {shop.slice(0, 2).toUpperCase()}
          </span>
        )}
        <div className="min-w-0">
          <p className="truncate text-base font-semibold text-ink-900">{shop}</p>
          <p className="mt-0.5 text-xs font-medium text-brand-700">{sw('Wakala', 'Agent')}</p>
          <p className="truncate text-xs text-ink-700">{user.name}</p>
        </div>
      </div>
      <p className="mt-4 truncate text-sm font-medium text-ink-800">
        {desk?.site.name ?? sw('Eneo', 'Site')}
        {desk?.site.ssid ? ` · ${desk.site.ssid}` : ''}
      </p>
      <div className="mt-2 grid grid-cols-3 gap-1.5">
        <RailStat label={sw('Leo', 'Today')} value={desk?.leo.kadi_count ?? '—'} />
        <RailStat label={sw('Mtandaoni', 'Online')} value={desk?.online.length ?? '—'} />
        <RailStat label={sw('Kadi', 'Cards')} value={desk?.remaining_cards ?? '—'} />
      </div>
      <SiteSwitch />
    </div>
  );
}

function AgentNav() {
  const { desk } = useAgentDesk();
  const { sw } = usePrefs();

  return (
    <nav className="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-4" aria-label="Desk ya wakala">
      {agentGroups.map((group) => (
        <div key={group.label}>
          {group.label ? (
            <p className="px-3 pb-2 text-[11px] font-semibold tracking-[0.16em] text-ink-700 uppercase">{group.label}</p>
          ) : null}
          <div className="space-y-1">
            {group.items.map((item) => (
              <NavLink key={item.to} to={item.to} end={item.end} className={agentLinkClass}>
                <NavIcon name={item.icon} />
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.95rem] leading-tight">{sw(item.kiswahili, item.english)}</span>
                  {item.hint ? <span className="agent-nav-hint block text-[11px] font-medium">{item.hint}</span> : null}
                </span>
                {item.count && (
                  <span className="agent-nav-count">{item.count(desk) ?? '—'}</span>
                )}
              </NavLink>
            ))}
          </div>
        </div>
      ))}
    </nav>
  );
}

function AgentFoot({ onLogout }: { onLogout: () => void }) {
  const { sw } = usePrefs();

  return (
    <div className="console-aside-foot space-y-2 p-4">
      <PrefBar />
      <button type="button" className={`${secondaryBtn} w-full`} onClick={onLogout}>
        {sw('Toka', 'Sign out')}
      </button>
    </div>
  );
}

export function AgentShell({ onLogout }: { onLogout: () => void }) {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const help = user?.tenant?.support_phone;

  return (
    <div className="console-shell bg-slate-50 text-ink-900 md:flex">
      <aside className="console-aside hidden md:sticky md:top-0 md:flex md:h-dvh md:w-72 md:shrink-0 md:flex-col md:overflow-hidden">
        {user && <Brand user={user} />}
        <AgentNav />
        {help && (
          <a href={`tel:${help}`} className="agent-nav-link mx-3 mb-2">
            <NavIcon name="help" />
            <span className="min-w-0">
              <span className="block leading-tight">{sw('Msaada', 'Help')}</span>
              <span className="agent-nav-hint block text-[11px] font-medium">{help}</span>
            </span>
          </a>
        )}
        <AgentFoot onLogout={onLogout} />
      </aside>

      <div className="flex min-w-0 flex-1 flex-col pb-[5.25rem] md:pb-0">
        <header className="console-mobile-header sticky top-0 z-20 border-b border-slate-200 md:hidden">
          <div className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-ink-900">
                {user?.tenant?.portal_name || user?.tenant?.name || 'Desk'}
              </p>
              <MobileSiteLabel />
            </div>
            <div className="flex shrink-0 items-center gap-2">
              <NavLink to="/desk/akaunti" className="text-sm font-semibold text-brand-700">
                {sw('Akaunti', 'Account')}
              </NavLink>
              <button type="button" className={secondaryBtn} onClick={onLogout}>
                {sw('Toka', 'Sign out')}
              </button>
            </div>
          </div>
          <div className="px-4 pb-3">
            <PrefBar />
          </div>
          <MobileRail />
          <SiteSwitch compact />
        </header>

        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 md:px-8">
          <Outlet />
        </main>
      </div>

      <nav
        className="console-tabbar fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 gap-0.5 border-t px-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 md:hidden"
        aria-label="Desk ya wakala"
      >
        {mobileTabs.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            className={({ isActive }) => `agent-tab ${isActive ? 'agent-nav-link-on' : ''}`}
          >
            <NavIcon name={item.icon} />
            <span>{sw(item.kiswahili, item.english)}</span>
          </NavLink>
        ))}
      </nav>
    </div>
  );
}

function MobileSiteLabel() {
  const { desk } = useAgentDesk();
  const { sw } = usePrefs();
  return (
    <p className="truncate text-xs text-ink-700">
      {desk?.site.name ?? sw('Wakala', 'Agent')}
      {desk?.site.ssid ? ` · ${desk.site.ssid}` : ''}
    </p>
  );
}

function MobileRail() {
  const { desk } = useAgentDesk();
  const { sw } = usePrefs();
  return (
    <div className="grid grid-cols-3 gap-2 px-4 pb-3">
      <div className="rounded-xl bg-slate-50 px-2 py-2 text-center">
        <p className="text-base font-bold text-ink-900">{desk?.leo.kadi_count ?? '—'}</p>
        <p className="text-[11px] font-medium text-ink-700">{sw('Leo', 'Today')}</p>
      </div>
      <div className="rounded-xl bg-slate-50 px-2 py-2 text-center">
        <p className="text-base font-bold text-ink-900">{desk?.online.length ?? '—'}</p>
        <p className="text-[11px] font-medium text-ink-700">{sw('Mtandaoni', 'Online')}</p>
      </div>
      <div className="rounded-xl bg-slate-50 px-2 py-2 text-center">
        <p className="text-base font-bold text-ink-900">{desk?.remaining_cards ?? '—'}</p>
        <p className="text-[11px] font-medium text-ink-700">{sw('Kadi', 'Cards')}</p>
      </div>
    </div>
  );
}

export function AgentLayout({ onLogout }: { onLogout: () => void }) {
  return (
    <AgentDeskProvider>
      <AgentShell onLogout={onLogout} />
    </AgentDeskProvider>
  );
}
