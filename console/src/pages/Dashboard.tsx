import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
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

  return (
    <div>
      <PageHeader title="Live network" subtitle="Concurrent sessions and today’s take." />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Online now" value={String(data?.concurrent_sessions ?? '—')} />
        <Stat label="Revenue today" value={data ? money(data.revenue_today_minor, currency) : '—'} />
        <Stat label="This month" value={data ? money(data.revenue_month_minor, currency) : '—'} />
        <Stat label="Vouchers activated" value={String(data?.vouchers_activated_today ?? '—')} />
      </div>
      <div className="mt-6 grid gap-4 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Revenue, last 14 days</h2>
          <Suspense fallback={<p className="text-sm text-ink-700">Loading chart…</p>}>
            <RevenueChart series={data?.revenue_series ?? []} currency={currency} />
          </Suspense>
        </Card>
        <Card className="lg:col-span-2">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Open sessions</h2>
          <ul className="space-y-2 text-sm">
            {(sessions.data?.data ?? []).slice(0, 8).map((session) => (
              <li key={session.acctuniqueid} className="flex justify-between gap-2">
                <span className="font-mono text-xs">{session.callingstationid || session.username.slice(-6)}</span>
                <span className="text-ink-700">{session.framedipaddress}</span>
              </li>
            ))}
            {(sessions.data?.data.length ?? 0) === 0 && <li className="text-ink-700">No one is online right now.</li>}
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
