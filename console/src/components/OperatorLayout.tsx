import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router';
import { isPlatformAdmin, useAuth } from '../auth';
import { usePrefs } from '../preferences';
import { NavIcon } from './navIcons';
import { PrefBar } from './PrefBar';
import { secondaryBtn } from './ui';

type NavItem = { to: string; label: string; end?: boolean; icon: string };

function navClass({ isActive }: { isActive: boolean }) {
  return `agent-nav-link ${isActive ? 'agent-nav-link-on' : ''}`;
}

function Foot({ showSettings }: { showSettings: boolean }) {
  const { sw } = usePrefs();
  const { logout } = useAuth();
  const navigate = useNavigate();

  return (
    <div className="console-aside-foot space-y-2 p-3">
      <PrefBar showSettings={showSettings} />
      <button
        type="button"
        className={`${secondaryBtn} w-full`}
        onClick={() => {
          void logout().then(() => navigate('/login', { replace: true }));
        }}
      >
        {sw('Toka', 'Sign out')}
      </button>
    </div>
  );
}

export function OperatorLayout() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const platform = isPlatformAdmin(user);
  const [menuOpen, setMenuOpen] = useState(false);
  const brand = user?.tenant?.portal_name || user?.tenant?.name || 'Kasi';
  const logo = user?.tenant?.logo_url;

  const items: NavItem[] = platform
    ? [
        { to: '/platform', label: sw('Muhtasari', 'Overview'), end: true, icon: 'live' },
        { to: '/platform/operators', label: sw('Waendeshaji', 'Operators'), icon: 'operators' },
        { to: '/platform/invoices', label: sw('Ankara', 'Invoices'), icon: 'invoice' },
      ]
    : [
        { to: '/', label: sw('Dashibodi', 'Dashboard'), end: true, icon: 'live' },
        { to: '/customers', label: sw('Wateja', 'Customers'), icon: 'people' },
        { to: '/plans', label: sw('Vifurushi', 'Packages'), icon: 'packages' },
        { to: '/batches', label: sw('Vocha', 'Vouchers'), icon: 'print' },
        { to: '/sites', label: sw('Maeneo', 'Sites'), icon: 'site' },
        { to: '/collections', label: sw('Makusanyo', 'Collections'), icon: 'cards' },
        { to: '/devices', label: sw('Vifaa', 'Devices'), icon: 'router' },
        { to: '/reports', label: sw('Ripoti', 'Reports'), icon: 'chart' },
        { to: '/billing', label: sw('Malipo ya mfumo', 'Platform billing'), icon: 'invoice' },
      ];

  const mobile = platform
    ? items
    : items.filter((item) => ['/', '/customers', '/batches', '/reports'].includes(item.to));

  const brandBlock = (
    <div className="console-aside-brand px-5 py-5">
      <div className="flex items-center gap-3">
        {logo && !platform ? (
          <img src={logo} alt="" className="h-11 w-11 rounded-2xl border border-slate-200 bg-white object-contain p-1" />
        ) : (
          <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-600 text-sm font-bold text-white">
            {(platform ? 'SA' : brand).slice(0, 2).toUpperCase()}
          </span>
        )}
        <div className="min-w-0">
          <p className="truncate text-base font-semibold text-ink-900">{platform ? 'Kasi Network' : brand}</p>
          <p className="truncate text-xs text-ink-700">{user?.name}</p>
        </div>
      </div>
    </div>
  );

  const nav = (
    <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Console">
      {items.map((item) => (
        <NavLink key={item.to} to={item.to} end={item.end} className={navClass} onClick={() => setMenuOpen(false)}>
          <NavIcon name={item.icon} />
          <span className="text-[0.95rem] leading-tight">{item.label}</span>
        </NavLink>
      ))}
    </nav>
  );

  return (
    <div className="console-shell bg-slate-50 text-ink-900 md:flex">
      <aside className="console-aside hidden md:sticky md:top-0 md:flex md:h-dvh md:w-64 md:shrink-0 md:flex-col md:overflow-hidden">
        {brandBlock}
        {nav}
        <Foot showSettings={!platform} />
      </aside>

      {menuOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <button type="button" className="absolute inset-0 bg-ink-950/50" aria-label={sw('Funga', 'Close')} onClick={() => setMenuOpen(false)} />
          <aside className="console-aside relative flex h-full w-80 max-w-[88%] flex-col shadow-xl">
            {brandBlock}
            {nav}
            <Foot showSettings={!platform} />
          </aside>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col pb-[5.25rem] md:pb-0">
        <header className="console-mobile-header sticky top-0 z-20 border-b border-slate-200 md:hidden">
          <div className="flex items-center justify-between gap-2 px-4 py-3">
            <p className="min-w-0 truncate text-sm font-semibold text-ink-900">{platform ? 'Super Admin' : brand}</p>
            <div className="flex shrink-0 items-center gap-1.5">
              <PrefBar compact />
              <button type="button" className={secondaryBtn} onClick={() => setMenuOpen(true)}>
                {sw('Menyu', 'Menu')}
              </button>
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 md:px-8">
          <Outlet />
        </main>
      </div>

      <nav
        className={`console-tabbar fixed inset-x-0 bottom-0 z-30 grid gap-0.5 border-t px-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 md:hidden ${platform ? 'grid-cols-4' : 'console-tabbar-5 grid-cols-5'}`}
        aria-label="Console"
      >
        {mobile.map((item) => (
          <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => `agent-tab ${isActive ? 'agent-nav-link-on' : ''}`}>
            <NavIcon name={item.icon} />
            <span>{item.label}</span>
          </NavLink>
        ))}
        {platform ? (
          <button type="button" className="agent-tab" onClick={() => setMenuOpen(true)}>
            <NavIcon name="settings" />
            <span>{sw('Menyu', 'Menu')}</span>
          </button>
        ) : (
          <NavLink to="/settings" className={({ isActive }) => `agent-tab ${isActive ? 'agent-nav-link-on' : ''}`}>
            <NavIcon name="settings" />
            <span>{sw('Mipangilio', 'Settings')}</span>
          </NavLink>
        )}
      </nav>
    </div>
  );
}
