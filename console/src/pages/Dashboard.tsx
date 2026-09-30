import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Card, PageHeader } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function DashboardPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const dash = useQuery({ queryKey: ['dashboard'], queryFn: api.dashboard, refetchInterval: 15_000 });
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: api.sessions, refetchInterval: 15_000 });

  if (dash.isError) {
    return <p className="text-red-700">{(dash.error as Error).message}</p>;
  }

  const data = dash.data;
  const currency = user?.tenant?.currency ?? 'TZS';

  return (
    <div>
      <PageHeader
        title={sw('Dashibodi', 'Dashboard')}
        subtitle={`${user?.tenant?.portal_name || user?.tenant?.name || sw('Mwendeshaji', 'Operator')} — ${sw('radio, Lipia, kadi, na kazi inayofuata.', 'radio, Lipia, cards, and what to do next.')}`}
      />
      
      <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
        <Link to="/customers">
          <Stat label={sw('Mtandaoni', 'Online')} value={String(data?.concurrent_sessions ?? '—')} hint={sw('Kata', 'Disconnect')} />
        </Link>
        <Link to="/collections">
          <Stat label={sw('Leo', 'Today')} value={data ? money(data.revenue_today_minor, currency) : '—'} hint={sw('Lipia + Vocha zilizotumika', 'Lipia + used Vouchers')} />
        </Link>
        <Link to="/reports">
          <Stat label={sw('Wiki hii', 'This week')} value={data ? money(data.week_minor ?? 0, currency) : '—'} />
        </Link>
        <Stat label={sw('Mwezi huu', 'This month')} value={data ? money(data.revenue_month_minor, currency) : '—'} />
      </div>
      <div className="mt-4 grid gap-3 sm:mt-6 sm:gap-4 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Siku 14', '14 days')}</h2>
            <Link to="/reports" className="text-sm font-semibold text-brand-700 hover:underline">
              {sw('Ripoti', 'Reports')}
            </Link>
          </div>
          <Suspense fallback={<p className="text-sm text-ink-700">{sw('Inapakia chati…', 'Loading chart…')}</p>}>
            <RevenueChart series={data?.revenue_series ?? []} currency={currency} />
          </Suspense>
        </Card>
        <Card className="lg:col-span-2">
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Mtandaoni sasa', 'Online now')}</h2>
            <Link to="/customers" className="text-sm font-semibold text-brand-700 hover:underline">
              {sw('Wateja', 'Customers')}
            </Link>
          </div>
          <ul className="space-y-2 text-sm">
            {(sessions.data?.data ?? []).slice(0, 8).map((session) => (
              <li key={session.acctuniqueid} className="flex justify-between gap-2">
                <span className="font-mono text-xs">{session.callingstationid || session.username.slice(-6)}</span>
                <span className="text-ink-700">{session.framedipaddress}</span>
              </li>
            ))}
            {(sessions.data?.data.length ?? 0) === 0 && <li className="text-ink-700">{sw('Hakuna aliye mtandaoni.', 'Nobody is online.')}</li>}
          </ul>
        </Card>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-2 sm:mt-4 sm:gap-3 lg:grid-cols-4">
        <Link to="/collections">
          <Stat label={sw('Lipia leo', 'Lipia today')} value={data ? money(data.lipia_today_minor ?? 0, currency) : '—'} hint={sw('Portal', 'Portal')} />
        </Link>
        <Link to="/collections">
          <Stat label={sw('Kadi leo', 'Cards today')} value={data ? money(data.kadi_today_minor ?? 0, currency) : '—'} hint={sw('Zilizotumika kwenye Wi‑Fi', 'Used on Wi‑Fi')} />
        </Link>
        <Link to="/batches">
          <Stat label={sw('Kadi hazijauzwa', 'Unsold cards')} value={String(data?.unused_cards ?? '—')} hint={sw('Stock', 'Stock')} />
        </Link>
        <Link to="/customers">
          <Stat label={sw('Hai', 'Active')} value={String(data?.hai_count ?? '—')} hint={`${data?.kimya_count ?? 0} ${sw('kimya', 'quiet')}`} />
        </Link>
      </div>
    </div>
  );
}

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <Card className="h-full transition hover:border-brand-400">
      <p className="text-[11px] leading-tight text-ink-700 sm:text-sm">{label}</p>
      <p className="mt-0.5 text-base font-bold leading-tight tracking-tight text-ink-900 sm:mt-1 sm:text-2xl">{value}</p>
      {hint && <p className="mt-0.5 line-clamp-2 text-[11px] leading-snug text-ink-700 sm:mt-1 sm:text-sm">{hint}</p>}
    </Card>
  );
}

