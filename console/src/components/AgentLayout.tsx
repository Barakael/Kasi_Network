import { NavLink, Outlet } from 'react-router';
import { AgentDeskProvider, useAgentDesk } from '../agent/desk';
import type { User } from '../api';
import { useAuth } from '../auth';
import { NavIcon, RailStat } from './navIcons';
import { inputClass, secondaryBtn } from './ui';

type NavItem = { to: string; label: string; hint: string; end?: boolean; icon: string; count?: (desk: ReturnType<typeof useAgentDesk>['desk']) => string | number | undefined };

const agentGroups: { label: string; items: NavItem[] }[] = [
  {
    label: 'Leo',
    items: [
      { to: '/desk', label: 'Live', hint: 'Kadi, pesa, mtandaoni', end: true, icon: 'live', count: (desk) => desk?.leo.kadi_count },
      { to: '/desk/analytics', label: 'Analytics', hint: 'Siku 14 na wiki', icon: 'chart', count: (desk) => desk?.week?.kadi_count },
    ],
  },
  {
    label: 'Uza',
    items: [{ to: '/desk/uza', label: 'Kadi', hint: 'Pokea pesa, chapisha, mpe mteja', icon: 'print', count: (desk) => desk?.remaining_cards }],
  },
  {
    label: 'Site',
    items: [
      { to: '/desk/online', label: 'Mtandaoni', hint: 'Waliounganishwa, Kata', icon: 'online', count: (desk) => desk?.online.length },
      { to: '/desk/wateja', label: 'Wateja', hint: 'Hai na Kimya', icon: 'people', count: (desk) => desk?.hai_count },
    ],
  },
];

const mobileTabs = agentGroups.flatMap((group) => group.items);

function agentLinkClass({ isActive }: { isActive: boolean }) {
  return `agent-nav-link ${isActive ? 'agent-nav-link-on' : ''}`;
}

function SiteSwitch({ compact = false }: { compact?: boolean }) {
  const { desk, siteId, setSiteId } = useAgentDesk();
  const sites = desk?.sites ?? [];
  if (sites.length < 2) {
    return null;
  }

  const select = (
    <label className={compact ? 'block' : 'mt-4 block'}>
      <span className="text-[11px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Site</span>
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
  const shop = user.tenant?.portal_name || user.tenant?.name || 'Kasi';
  const logo = user.tenant?.logo_url;

  return (
    <div className="console-aside-brand px-5 py-6">
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
          <p className="mt-0.5 text-xs font-medium text-brand-700">Wakala</p>
          <p className="truncate text-xs text-ink-700">{user.name}</p>
        </div>
      </div>
      <p className="mt-4 truncate text-sm font-medium text-ink-800">
        {desk?.site.name ?? 'Site'}
        {desk?.site.ssid ? ` · ${desk.site.ssid}` : ''}
      </p>
      <div className="mt-4 grid grid-cols-3 gap-1.5">
        <RailStat label="Leo" value={desk?.leo.kadi_count ?? '—'} />
        <RailStat label="Online" value={desk?.online.length ?? '—'} />
        <RailStat label="Kadi" value={desk?.remaining_cards ?? '—'} />
      </div>
      <SiteSwitch />
    </div>
  );
}

function AgentNav() {
  const { desk } = useAgentDesk();

  return (
    <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Desk ya wakala">
      {agentGroups.map((group) => (
        <div key={group.label}>
          <p className="px-3 pb-2 text-[11px] font-semibold tracking-[0.16em] text-ink-700 uppercase">{group.label}</p>
          <div className="space-y-1">
            {group.items.map((item) => (
              <NavLink key={item.to} to={item.to} end={item.end} className={agentLinkClass}>
                <NavIcon name={item.icon} />
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.95rem] leading-tight">{item.label}</span>
                  <span className="agent-nav-hint block text-[11px] font-medium">{item.hint}</span>
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

export function AgentShell({ onLogout }: { onLogout: () => void }) {
  const { user } = useAuth();
  const help = user?.tenant?.support_phone;

  return (
    <div className="console-shell bg-slate-50 text-ink-900 md:flex">
      <aside className="console-aside hidden md:sticky md:top-0 md:flex md:h-dvh md:w-72 md:shrink-0 md:flex-col">
        {user && <Brand user={user} />}
        <AgentNav />
        <div className="console-aside-foot space-y-2 p-3">
          <NavLink to="/desk/akaunti" className={agentLinkClass}>
            <NavIcon name="account" />
            <span className="min-w-0">
              <span className="block leading-tight">Akaunti</span>
              <span className="agent-nav-hint block text-[11px] font-medium">Nenosiri</span>
            </span>
          </NavLink>
          {help && (
            <a href={`tel:${help}`} className="agent-nav-link">
              <NavIcon name="help" />
              <span className="min-w-0">
                <span className="block leading-tight">Msaada</span>
                <span className="agent-nav-hint block text-[11px] font-medium">{help}</span>
              </span>
            </a>
          )}
          <button type="button" className={`${secondaryBtn} w-full`} onClick={onLogout}>
            Sign out
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col pb-[5.25rem] md:pb-0">
        <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur md:hidden">
          <div className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-ink-900">
                {user?.tenant?.portal_name || user?.tenant?.name || 'Desk'}
              </p>
              <MobileSiteLabel />
            </div>
            <div className="flex shrink-0 items-center gap-2">
              <NavLink to="/desk/akaunti" className="text-sm font-semibold text-brand-700">
                Akaunti
              </NavLink>
              <button type="button" className={secondaryBtn} onClick={onLogout}>
                Sign out
              </button>
            </div>
          </div>
          <MobileRail />
          <SiteSwitch compact />
        </header>

        <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-6 md:px-8">
          <Outlet />
        </main>
      </div>

      <nav
        className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 gap-0.5 border-t border-slate-200 bg-white px-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 md:hidden"
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
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>
    </div>
  );
}

function MobileSiteLabel() {
  const { desk } = useAgentDesk();
  return (
    <p className="truncate text-xs text-ink-700">
      {desk?.site.name ?? 'Wakala'}
      {desk?.site.ssid ? ` · ${desk.site.ssid}` : ''}
    </p>
  );
}

function MobileRail() {
  const { desk } = useAgentDesk();
  return (
    <div className="grid grid-cols-3 gap-2 px-4 pb-3">
      <div className="rounded-xl bg-slate-50 px-2 py-2 text-center">
        <p className="text-base font-bold text-ink-900">{desk?.leo.kadi_count ?? '—'}</p>
        <p className="text-[11px] font-medium text-ink-700">Leo</p>
      </div>
      <div className="rounded-xl bg-slate-50 px-2 py-2 text-center">
        <p className="text-base font-bold text-ink-900">{desk?.online.length ?? '—'}</p>
        <p className="text-[11px] font-medium text-ink-700">Online</p>
      </div>
      <div className="rounded-xl bg-slate-50 px-2 py-2 text-center">
        <p className="text-base font-bold text-ink-900">{desk?.remaining_cards ?? '—'}</p>
        <p className="text-[11px] font-medium text-ink-700">Kadi</p>
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
