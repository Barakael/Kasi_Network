import { Link } from 'react-router';
import { lazy, Suspense } from 'react';
import { useAgentDesk } from '../agent/desk';
import { Card, Empty, ErrorBanner, PageHeader, primaryBtn } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function AgentDeskPage() {
  const { user } = useAuth();
  const { desk, isLoading, error } = useAgentDesk();
  const currency = user?.tenant?.currency ?? 'TZS';
  const nextPack = desk?.batches.find((batch) => batch.is_printable);
  const lowStock = (desk?.remaining_cards ?? 0) > 0 && (desk?.remaining_cards ?? 0) <= 10;
  const week = desk?.week?.kadi_minor ?? 0;
  const previous = desk?.previous_week?.kadi_minor ?? 0;

  return (
    <div>
      <PageHeader
        title="Live"
        subtitle={`${desk?.site.name ?? 'Site yako'} — pesa mkononi, kadi, na hali ya radio.`}
      />
      <ErrorBanner message={error?.message ?? null} />
      {desk?.router_quiet && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
          Router kimya — RADIUS haijaona paketi kwenye site hii. Vocha inaweza kuwa sawa, radio ndiyo imesimama.
        </p>
      )}
      {lowStock && (
        <p className="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700" role="status">
          Kadi chache: {desk?.remaining_cards} zimebaki. Uza au omba pack mpya.
        </p>
      )}
      {isLoading && !desk ? (
        <Empty>Inapakia desk…</Empty>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Stat label="Kadi za kuuza" value={String(desk?.remaining_cards ?? '—')} hint="Uza" to="/desk/uza" />
            <Stat
              label="Leo"
              value={desk ? money(desk.leo.kadi_minor, currency) : '—'}
              hint={`${desk?.leo.kadi_count ?? 0} zilizouzwa · ${desk?.leo.used_count ?? 0} zimetumika`}
            />
            <Stat
              label="Wiki hii"
              value={desk ? money(week, currency) : '—'}
              hint={previous === 0 ? `${desk?.week?.kadi_count ?? 0} kadi` : `${desk?.week?.kadi_count ?? 0} kadi · vs ${money(previous, currency)}`}
              to="/desk/analytics"
            />
            <Stat label="Mtandaoni" value={String(desk?.online.length ?? '—')} hint="Kata" to="/desk/online" />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-5">
            <Card className="lg:col-span-3">
              <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Siku 14 — kadi</h2>
                <Link to="/desk/analytics" className="text-sm font-semibold text-brand-700 hover:underline">
                  Analytics
                </Link>
              </div>
              <Suspense fallback={<p className="text-sm text-ink-700">Inapakia chati…</p>}>
                <RevenueChart series={desk?.series ?? []} currency={currency} empty="Hakuna kadi zilizouzwa katika siku 14." />
              </Suspense>
            </Card>
            <Card className="lg:col-span-2">
              <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Mtandaoni sasa</h2>
                <Link to="/desk/online" className="text-sm font-semibold text-brand-700 hover:underline">
                  Orodha
                </Link>
              </div>
              <ul className="space-y-2">
                {(desk?.online ?? []).slice(0, 5).map((session) => (
                  <li key={session.acctuniqueid} className="flex justify-between gap-2 text-sm">
                    <span className="font-mono text-ink-900">{session.callingstationid || session.username.slice(-6)}</span>
                    <span className="text-ink-700">{session.framedipaddress}</span>
                  </li>
                ))}
              </ul>
              {(desk?.online.length ?? 0) === 0 && <Empty>Hakuna aliye mtandaoni.</Empty>}
            </Card>
          </div>

          <Card className="mt-4">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Uza sasa</h2>
              <Link to="/desk/uza" className="text-sm font-semibold text-brand-700 hover:underline">
                Packs zote
              </Link>
            </div>
            {nextPack ? (
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-lg font-semibold text-ink-900">{nextPack.plan?.name ?? nextPack.reference}</p>
                  <p className="text-sm text-ink-700">
                    {nextPack.plan?.price_minor != null ? money(nextPack.plan.price_minor, currency) : nextPack.reference}
                    {' · '}
                    {nextPack.printable_count ?? 0} kadi zimebaki
                  </p>
                </div>
                <Link to="/desk/uza" className={primaryBtn}>
                  Uza kadi
                </Link>
              </div>
            ) : (
              <Empty>Bado hujapewa kadi za kuuza kwenye site hii. Omba pack kwa msimamizi.</Empty>
            )}
          </Card>
        </>
      )}
    </div>
  );
}

function Stat({
  label,
  value,
  hint,
  to,
}: {
  label: string;
  value: string;
  hint?: string;
  to?: string;
}) {
  const body = (
    <Card className={to ? 'transition hover:border-brand-400' : ''}>
      <p className="text-sm text-ink-700">{label}</p>
      <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{value}</p>
      {hint && <p className="mt-1 text-sm text-ink-700">{hint}</p>}
    </Card>
  );

  return to ? <Link to={to}>{body}</Link> : body;
}
