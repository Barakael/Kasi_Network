import { lazy, Suspense } from 'react';
import { useAgentDesk } from '../agent/desk';
import { Card, Empty, ErrorBanner, PageHeader } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function AgentAnalyticsPage() {
  const { user } = useAuth();
  const { desk, isLoading, error } = useAgentDesk();
  const currency = user?.tenant?.currency ?? 'TZS';
  const week = desk?.week?.kadi_minor ?? 0;
  const previous = desk?.previous_week?.kadi_minor ?? 0;

  return (
    <div>
      <PageHeader
        title="Analytics"
        subtitle={`${desk?.site.name ?? 'Site yako'} — pesa uliyopokea wiki hii, wiki iliyopita, na siku 14.`}
      />
      <ErrorBanner message={error?.message ?? null} />
      {isLoading && !desk ? (
        <Empty>Inapakia analytics…</Empty>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <p className="text-sm text-ink-700">Leo</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">
                {desk ? money(desk.leo.kadi_minor, currency) : '—'}
              </p>
              <p className="mt-1 text-sm text-ink-700">
                {desk?.leo.kadi_count ?? 0} zilizouzwa · {desk?.leo.used_count ?? 0} zimetumika
              </p>
            </Card>
            <Card>
              <p className="text-sm text-ink-700">Wiki hii</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{desk ? money(week, currency) : '—'}</p>
              <p className="mt-1 text-sm text-ink-700">{desk?.week?.kadi_count ?? 0} kadi</p>
            </Card>
            <Card>
              <p className="text-sm text-ink-700">Wiki iliyopita</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{desk ? money(previous, currency) : '—'}</p>
              <p className="mt-1 text-sm text-ink-700">{vsLastWeek(week, previous)}</p>
            </Card>
            <Card>
              <p className="text-sm text-ink-700">Wateja</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{desk?.hai_count ?? 0}</p>
              <p className="mt-1 text-sm text-ink-700">{desk?.kimya_count ?? 0} kimya · {desk?.online.length ?? 0} mtandaoni</p>
            </Card>
          </div>
          <Card className="mt-6">
            <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Siku 14 — kadi</h2>
            <Suspense fallback={<p className="text-sm text-ink-700">Inapakia chati…</p>}>
              <RevenueChart series={desk?.series ?? []} currency={currency} empty="Hakuna kadi zilizouzwa katika siku 14." />
            </Suspense>
          </Card>
        </>
      )}
    </div>
  );
}

function vsLastWeek(current: number, previous: number): string {
  if (previous === 0) {
    return current === 0 ? 'Sawa na wiki iliyopita' : 'Hakuna kulinganisha bado';
  }
  const pct = Math.round(((current - previous) / previous) * 100);

  return pct >= 0 ? `+${pct}% vs wiki iliyopita` : `${pct}% vs wiki iliyopita`;
}
