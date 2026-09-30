import { lazy, Suspense } from 'react';
import { useAgentDesk } from '../agent/desk';
import { Card, Empty, ErrorBanner, PageHeader } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function AgentAnalyticsPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const { desk, isLoading, error } = useAgentDesk();
  const currency = user?.tenant?.currency ?? 'TZS';
  const week = desk?.week?.kadi_minor ?? 0;
  const previous = desk?.previous_week?.kadi_minor ?? 0;

  return (
    <div>
      <PageHeader
        title={sw('Ripoti', 'Reports')}
        subtitle={`${desk?.site.name ?? sw('Eneo lako', 'Your site')} — ${sw('pesa uliyopokea wiki hii, wiki iliyopita, na siku 14.', 'money you took this week, last week, and over 14 days.')}`}
      />
      <ErrorBanner message={error?.message ?? null} />
      {isLoading && !desk ? (
        <Empty>{sw('Inapakia ripoti…', 'Loading the report…')}</Empty>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <p className="text-sm text-ink-700">{sw('Leo', 'Today')}</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">
                {desk ? money(desk.leo.kadi_minor, currency) : '—'}
              </p>
              <p className="mt-1 text-sm text-ink-700">
                {desk?.leo.kadi_count ?? 0} {sw('zilizouzwa', 'sold')} · {desk?.leo.used_count ?? 0} {sw('zimetumika', 'used')}
              </p>
            </Card>
            <Card>
              <p className="text-sm text-ink-700">{sw('Wiki hii', 'This week')}</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{desk ? money(week, currency) : '—'}</p>
              <p className="mt-1 text-sm text-ink-700">{desk?.week?.kadi_count ?? 0} {sw('kadi', 'cards')}</p>
            </Card>
            <Card>
              <p className="text-sm text-ink-700">{sw('Wiki iliyopita', 'Last week')}</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{desk ? money(previous, currency) : '—'}</p>
              <p className="mt-1 text-sm text-ink-700">{vsLastWeek(week, previous, sw)}</p>
            </Card>
            <Card>
              <p className="text-sm text-ink-700">{sw('Wateja', 'Customers')}</p>
              <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{desk?.hai_count ?? 0}</p>
              <p className="mt-1 text-sm text-ink-700">{desk?.kimya_count ?? 0} {sw('kimya', 'quiet')} · {desk?.online.length ?? 0} {sw('mtandaoni', 'online')}</p>
            </Card>
          </div>
          <Card className="mt-6">
            <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Siku 14 — kadi', '14 days — cards')}</h2>
            <Suspense fallback={<p className="text-sm text-ink-700">{sw('Inapakia chati…', 'Loading chart…')}</p>}>
              <RevenueChart series={desk?.series ?? []} currency={currency} empty={sw('Hakuna kadi zilizouzwa katika siku 14.', 'No cards sold in 14 days.')} />
            </Suspense>
          </Card>
        </>
      )}
    </div>
  );
}

function vsLastWeek(current: number, previous: number, sw: (kiswahili: string, english: string) => string): string {
  if (previous === 0) {
    return current === 0 ? sw('Sawa na wiki iliyopita', 'Same as last week') : sw('Hakuna kulinganisha bado', 'Nothing to compare yet');
  }
  const pct = Math.round(((current - previous) / previous) * 100);
  const against = sw('vs wiki iliyopita', 'vs last week');

  return pct >= 0 ? `+${pct}% ${against}` : `${pct}% ${against}`;
}
