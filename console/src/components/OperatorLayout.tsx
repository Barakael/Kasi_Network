import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { NavLink, Outlet, useNavigate } from 'react-router';
import { api } from '../api';
import { isPlatformAdmin, useAuth } from '../auth';
import { NavIcon, RailStat } from './navIcons';
import { secondaryBtn } from './ui';

type NavItem = { to: string; label: string; hint: string; end?: boolean; icon: string; count?: string | number };

const platformGroups: { label: string; items: NavItem[] }[] = [
  {
    label: 'Platform',
    items: [
      { to: '/platform', label: 'Overview', hint: 'Operators, Lipia, bili', end: true, icon: 'live' },
      { to: '/platform/operators', label: 'Operators', hint: 'Invite System Admins', icon: 'operators' },
      { to: '/platform/invoices', label: 'Invoices', hint: 'Bili kwa operator', icon: 'invoice' },
    ],
  },
];

const operatorMobile = [
  { to: '/', label: 'Live', end: true, icon: 'live' },
  { to: '/analytics', label: 'Analytics', icon: 'chart' },
  { to: '/customers', label: 'Wateja', icon: 'people' },
];

const platformMobile = [
  { to: '/platform', label: 'Overview', end: true, icon: 'live' },
  { to: '/platform/operators', label: 'Operators', icon: 'operators' },
  { to: '/platform/invoices', label: 'Invoices', icon: 'invoice' },
];

function navClass({ isActive }: { isActive: boolean }) {
  return `agent-nav-link ${isActive ? 'agent-nav-link-on' : ''}`;
}

export function OperatorLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const platform = isPlatformAdmin(user);
  const [menuOpen, setMenuOpen] = useState(false);
  const brand = user?.tenant?.portal_name || user?.tenant?.name || 'Kasi';
  const logo = user?.tenant?.logo_url;
  const dash = useQuery({ queryKey: ['dashboard'], queryFn: api.dashboard, enabled: !platform, refetchInterval: 15_000 });
  const overview = useQuery({
    queryKey: ['platform-overview'],
    queryFn: api.platformOverview,
    enabled: platform,
    refetchInterval: 30_000,
  });

  const operatorGroups: { label: string; items: NavItem[] }[] = [
    {
      label: 'Leo',
      items: [
        { to: '/', label: 'Live', hint: 'Radio, pesa, kazi', end: true, icon: 'live', count: dash.data?.concurrent_sessions },
        { to: '/analytics', label: 'Analytics', hint: 'Siku 14, wiki, mwezi', icon: 'chart' },
      ],
    },
    {
      label: 'Operesheni',
      items: [
        { to: '/sessions', label: 'Mtandaoni', hint: 'Sessions, Kata', icon: 'online', count: dash.data?.concurrent_sessions },
        { to: '/customers', label: 'Wateja', hint: 'Hai na Kimya', icon: 'people', count: dash.data?.hai_count },
        { to: '/devices', label: 'Vifaa', hint: 'MAC kwenye kadi', icon: 'router' },
      ],
    },
    {
      label: 'Stock',
      items: [
        { to: '/plans', label: 'Packages', hint: 'Bei na ofa', icon: 'packages' },
        { to: '/batches', label: 'Packs', hint: 'Tengeneza, peleka wakala', icon: 'print', count: dash.data?.unused_cards },
        { to: '/agents', label: 'Mawakala', hint: 'Site, kisha pack', icon: 'people' },
      ],
    },
    {
      label: 'Mtandao',
      items: [
        { to: '/sites', label: 'Sites', hint: 'SSID na mawakala', icon: 'site' },
        { to: '/routers', label: 'Routers', hint: 'Snippet, RADIUS', icon: 'router' },
      ],
    },
    {
      label: 'Biashara',
      items: [
        { to: '/collections', label: 'Collections', hint: 'Lipia vs kadi', icon: 'cards' },
        { to: '/campaigns', label: 'Notices', hint: 'Ujumbe kwenye portal', icon: 'notice' },
        { to: '/billing', label: 'Billing', hint: 'Bili kwa Kasi', icon: 'invoice' },
        { to: '/settings', label: 'Settings', hint: 'Jina, logo, PalmPesa', icon: 'settings' },
      ],
    },
  ];

  const groups = platform ? platformGroups : operatorGroups;

  async function onLogout() {
    await logout();
    navigate('/login', { replace: true });
  }

  const brandBlock = (
    <div className="console-aside-brand px-5 py-6">
      <div className="flex items-start gap-3">
        {logo && !platform ? (
          <img src={logo} alt="" className="h-12 w-12 rounded-2xl border border-slate-200 bg-white object-contain p-1" />
        ) : (
          <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-sm font-bold text-white">
            {(platform ? 'SA' : brand).slice(0, 2).toUpperCase()}
          </span>
        )}
        <div className="min-w-0">
          <p className="truncate text-base font-semibold text-ink-900">{platform ? 'Kasi Network' : brand}</p>
          <p className="mt-0.5 text-xs font-medium text-brand-700">{platform ? 'Super Admin' : 'System Administrator'}</p>
          <p className="truncate text-xs text-ink-700">{user?.name}</p>
        </div>
      </div>
      <div className="mt-4 grid grid-cols-3 gap-1.5">
        {platform ? (
          <>
            <RailStat label="Ops" value={overview.data?.operators_active ?? '—'} />
            <RailStat label="Lipia" value={overview.data?.lipia_on ?? '—'} />
            <RailStat label="Bili" value={overview.data?.invoices_open ?? '—'} />
          </>
        ) : (
          <>
            <RailStat label="Online" value={dash.data?.concurrent_sessions ?? '—'} />
            <RailStat label="Hai" value={dash.data?.hai_count ?? '—'} />
            <RailStat label="Stock" value={dash.data?.unused_cards ?? '—'} />
          </>
        )}
      </div>
    </div>
  );

  const nav = (
    <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Console">
      {groups.map((group) => (
        <div key={group.label}>
          <p className="px-3 pb-2 text-[11px] font-semibold tracking-[0.16em] text-ink-700 uppercase">{group.label}</p>
          <div className="space-y-1">
            {group.items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={navClass}
                onClick={() => setMenuOpen(false)}
              >
                <NavIcon name={item.icon} />
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.95rem] leading-tight">{item.label}</span>
                  <span className="agent-nav-hint block text-[11px] font-medium">{item.hint}</span>
                </span>
                {item.count !== undefined && item.count !== '' && (
                  <span className="agent-nav-count">{item.count ?? '—'}</span>
                )}
              </NavLink>
            ))}
          </div>
        </div>
      ))}
    </nav>
  );

  const foot = (
    <div className="console-aside-foot space-y-2 p-3">
      <NavLink to="/account" className={navClass} onClick={() => setMenuOpen(false)}>
        <NavIcon name="account" />
        <span className="min-w-0">
          <span className="block leading-tight">Akaunti</span>
          <span className="agent-nav-hint block text-[11px] font-medium">Nenosiri</span>
        </span>
      </NavLink>
      <button type="button" className={`${secondaryBtn} w-full`} onClick={() => void onLogout()}>
        Sign out
      </button>
    </div>
  );

  return (
    <div className="console-shell bg-slate-50 text-ink-900 md:flex">
      <aside className="console-aside hidden md:sticky md:top-0 md:flex md:h-dvh md:w-72 md:shrink-0 md:flex-col">
        {brandBlock}
        {nav}
        {foot}
      </aside>

      {menuOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <button type="button" className="absolute inset-0 bg-ink-950/50" aria-label="Funga menyu" onClick={() => setMenuOpen(false)} />
          <aside className="console-aside relative flex h-full w-80 max-w-[88%] flex-col shadow-xl">
            {brandBlock}
            {nav}
            {foot}
          </aside>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col pb-[5.25rem] md:pb-0">
        <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur md:hidden">
          <div className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-ink-900">{platform ? 'Super Admin' : brand}</p>
              <p className="truncate text-xs text-ink-700">{user?.name}</p>
            </div>
            <button type="button" className={secondaryBtn} onClick={() => setMenuOpen(true)}>
              Menyu
            </button>
          </div>
        </header>
        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 md:px-8">
          <Outlet />
        </main>
      </div>

      <nav
        className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 gap-0.5 border-t border-slate-200 bg-white px-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 md:hidden"
        aria-label="Console"
      >
        {(platform ? platformMobile : operatorMobile).map((item) => (
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
        <button type="button" className="agent-tab" onClick={() => setMenuOpen(true)}>
          <NavIcon name="settings" />
          <span>Menyu</span>
        </button>
      </nav>
    </div>
  );
}
