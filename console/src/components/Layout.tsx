import { NavLink, Outlet, useNavigate } from 'react-router';
import { isAgent, useAuth } from '../auth';
import { secondaryBtn } from './ui';

const operatorLinks = [
  ['/', 'Live'],
  ['/sessions', 'Sessions'],
  ['/plans', 'Bundles'],
  ['/batches', 'Vouchers'],
  ['/routers', 'Routers'],
  ['/devices', 'Devices'],
  ['/reports', 'Reports'],
] as const;

export function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const agent = isAgent(user);

  async function onLogout() {
    await logout();
    navigate('/login', { replace: true });
  }

  return (
    <div className="min-h-dvh bg-slate-50 text-ink-900">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
          <div>
            <p className="text-sm font-semibold text-brand-700">Kasi Network</p>
            <p className="text-xs text-ink-700">{user?.tenant?.name ?? user?.name}</p>
          </div>
          <button type="button" className={secondaryBtn} onClick={() => void onLogout()}>
            Sign out
          </button>
        </div>
        {!agent && (
          <nav className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4 pb-2">
            {operatorLinks.map(([to, label]) => (
              <NavLink
                key={to}
                to={to}
                end={to === '/'}
                className={({ isActive }) =>
                  `whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold ${
                    isActive ? 'bg-brand-600 text-white' : 'text-ink-800 hover:bg-slate-100'
                  }`
                }
              >
                {label}
              </NavLink>
            ))}
          </nav>
        )}
      </header>
      <main className="mx-auto max-w-6xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  );
}
