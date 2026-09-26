import { NavLink, Outlet, useNavigate } from 'react-router';
import { isAgent, useAuth } from '../auth';
import { secondaryBtn } from './ui';

type NavItem = readonly [to: string, label: string];

const operatorGroups: { label: string; items: readonly NavItem[] }[] = [
  {
    label: 'Monitor',
    items: [
      ['/', 'Live'],
      ['/sessions', 'Sessions'],
    ],
  },
  {
    label: 'Sell',
    items: [
      ['/plans', 'Bundles'],
      ['/batches', 'Vouchers'],
    ],
  },
  {
    label: 'Network',
    items: [
      ['/routers', 'Routers'],
      ['/devices', 'Devices'],
    ],
  },
  {
    label: 'Business',
    items: [['/reports', 'Reports']],
  },
];

const operatorLinks = operatorGroups.flatMap((group) => group.items);

function linkClass({ isActive }: { isActive: boolean }) {
  return `console-nav-link justify-center text-center md:justify-start md:text-left ${isActive ? 'bg-brand-600 text-white' : 'text-ink-800 hover:bg-slate-100'}`;
}

export function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const agent = isAgent(user);

  async function onLogout() {
    await logout();
    navigate('/login', { replace: true });
  }

  return (
    <div className="console-shell bg-slate-50 text-ink-900 md:flex">
      <aside className="hidden md:sticky md:top-0 md:flex md:h-dvh md:w-60 md:shrink-0 md:flex-col md:border-r md:border-slate-200 md:bg-white">
        <div className="border-b border-slate-200 px-5 py-5">
          <p className="text-sm font-semibold text-brand-700">Kasi Network</p>
          <p className="mt-1 text-base font-semibold text-ink-900">{user?.tenant?.name ?? 'Console'}</p>
          <p className="mt-0.5 text-xs text-ink-700">
            {user?.name}
            {user?.role ? ` · ${user.role}` : ''}
          </p>
        </div>
        <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Console">
          {agent ? (
            <NavLink to="/print" className={linkClass}>
              Print stock
            </NavLink>
          ) : (
            operatorGroups.map((group) => (
              <div key={group.label}>
                <p className="px-3 pb-1 text-[11px] font-semibold tracking-[0.14em] text-ink-700 uppercase">{group.label}</p>
                <div className="space-y-0.5">
                  {group.items.map(([to, label]) => (
                    <NavLink key={to} to={to} end={to === '/'} className={linkClass}>
                      {label}
                    </NavLink>
                  ))}
                </div>
              </div>
            ))
          )}
        </nav>
        <div className="border-t border-slate-200 p-3">
          <button type="button" className={`${secondaryBtn} w-full`} onClick={() => void onLogout()}>
            Sign out
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur md:hidden">
          <div className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
              <p className="text-sm font-semibold text-brand-700">Kasi Network</p>
              <p className="truncate text-xs text-ink-700">
                {user?.tenant?.name ?? user?.name}
                {user?.role ? ` · ${user.role}` : ''}
              </p>
            </div>
            <button type="button" className={secondaryBtn} onClick={() => void onLogout()}>
              Sign out
            </button>
          </div>
          <nav className="grid grid-cols-3 gap-1 px-3 pb-3" aria-label="Console">
            {agent ? (
              <NavLink to="/print" className={linkClass}>
                Print stock
              </NavLink>
            ) : (
              operatorLinks.map(([to, label]) => (
                <NavLink key={to} to={to} end={to === '/'} className={linkClass}>
                  {label}
                </NavLink>
              ))
            )}
          </nav>
        </header>

        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 md:px-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
