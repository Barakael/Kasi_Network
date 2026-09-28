import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Card, PageHeader } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function DashboardPage() {
  const { user } = useAuth();
  const dash = useQuery({ queryKey: ['dashboard'], queryFn: api.dashboard, refetchInterval: 15_000 });
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: api.sessions, refetchInterval: 15_000 });

  if (dash.isError) {
    return <p className="text-red-700">{(dash.error as Error).message}</p>;
  }

  const data = dash.data;
  const currency = user?.tenant?.currency ?? 'TZS';
  const quiet = data?.router_quiet;

  return (
    <div>
      <PageHeader title="Live" subtitle="Leo: Lipia, kadi, watu mtandaoni, na hali ya router." />
      {quiet && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
          Router kimya — RADIUS haijaona paketi. 0 TZS inaweza kuwa radio, si wateja.
        </p>
      )}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Link to="/sessions">
          <Stat label="Mtandaoni" value={String(data?.concurrent_sessions ?? '—')} />
        </Link>
        <Link to="/collections">
          <Stat label="Leo (Lipia + kadi)" value={data ? money(data.revenue_today_minor, currency) : '—'} />
        </Link>
        <Stat label="Lipia leo" value={data ? money(data.lipia_today_minor ?? 0, currency) : '—'} />
        <Stat label="Kadi leo" value={data ? money(data.kadi_today_minor ?? 0, currency) : '—'} />
        <Stat label="Kadi hazijauzwa" value={String(data?.unused_cards ?? '—')} />
        <Stat label="Mwezi huu" value={data ? money(data.revenue_month_minor, currency) : '—'} />
      </div>
      <div className="mt-6 grid gap-4 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Siku 14</h2>
          <Suspense fallback={<p className="text-sm text-ink-700">Loading chart…</p>}>
            <RevenueChart series={data?.revenue_series ?? []} currency={currency} />
          </Suspense>
        </Card>
        <Card className="lg:col-span-2">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Mtandaoni sasa</h2>
          <ul className="space-y-2 text-sm">
            {(sessions.data?.data ?? []).slice(0, 8).map((session) => (
              <li key={session.acctuniqueid} className="flex justify-between gap-2">
                <span className="font-mono text-xs">{session.callingstationid || session.username.slice(-6)}</span>
                <span className="text-ink-700">{session.framedipaddress}</span>
              </li>
            ))}
            {(sessions.data?.data.length ?? 0) === 0 && <li className="text-ink-700">Hakuna aliye mtandaoni.</li>}
          </ul>
        </Card>
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <p className="text-sm text-ink-700">{label}</p>
      <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{value}</p>
    </Card>
  );
}
