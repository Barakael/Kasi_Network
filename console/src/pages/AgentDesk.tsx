import { Link } from 'react-router';
import { lazy, Suspense } from 'react';
import { useAgentDesk } from '../agent/desk';
import { Card, Empty, ErrorBanner, PageHeader, primaryBtn } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function AgentDeskPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const { desk, isLoading, error } = useAgentDesk();
  const currency = user?.tenant?.currency ?? 'TZS';
  const nextPack = desk?.batches.find((batch) => batch.is_printable);
  const lowStock = (desk?.remaining_cards ?? 0) > 0 && (desk?.remaining_cards ?? 0) <= 10;
  const week = desk?.week?.kadi_minor ?? 0;
  const previous = desk?.previous_week?.kadi_minor ?? 0;

  return (
    <div>
      <PageHeader
        title={sw('Dashibodi', 'Dashboard')}
        subtitle={`${desk?.site.name ?? sw('Eneo lako', 'Your site')} — ${sw('pesa mkononi, kadi, na hali ya radio.', 'cash in hand, cards, and the radio.')}`}
      />
      <ErrorBanner message={error?.message ?? null} />
      {desk?.router_quiet && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
          {sw('Router kimya — RADIUS haijaona paketi kwenye eneo hili. Vocha inaweza kuwa sawa, radio ndiyo imesimama.', 'The router is quiet — RADIUS has not seen a packet at this site. Vouchers may be fine; the radio is what stopped.')}
        </p>
      )}
      {lowStock && (
        <p className="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700" role="status">
          {sw('Kadi chache', 'Few cards left')}: {desk?.remaining_cards} {sw('zimebaki. Uza au omba vocha mpya.', 'left. Sell them or ask for a new pack.')}
        </p>
      )}
      {isLoading && !desk ? (
        <Empty>{sw('Inapakia desk…', 'Loading the desk…')}</Empty>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
            <Stat label={sw('Kadi za kuuza', 'Cards to sell')} value={String(desk?.remaining_cards ?? '—')} hint={sw('Uza', 'Sell')} to="/desk/uza" />
            <Stat
              label={sw('Leo', 'Today')}
              value={desk ? money(desk.leo.kadi_minor, currency) : '—'}
              hint={`${desk?.leo.kadi_count ?? 0} ${sw('zilizouzwa', 'sold')} · ${desk?.leo.used_count ?? 0} ${sw('zimetumika', 'used')}`}
            />
            <Stat
              label={sw('Wiki hii', 'This week')}
              value={desk ? money(week, currency) : '—'}
              hint={previous === 0 ? `${desk?.week?.kadi_count ?? 0} ${sw('kadi', 'cards')}` : `${desk?.week?.kadi_count ?? 0} ${sw('kadi', 'cards')} · vs ${money(previous, currency)}`}
              to="/desk/analytics"
            />
            <Stat label={sw('Mtandaoni', 'Online')} value={String(desk?.online.length ?? '—')} hint={sw('Kata', 'Disconnect')} to="/desk/wateja" />
          </div>

          <div className="mt-6 grid gap-4 lg:grid-cols-5">
            <Card className="lg:col-span-3">
              <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Siku 14 — kadi', '14 days — cards')}</h2>
                <Link to="/desk/analytics" className="text-sm font-semibold text-brand-700 hover:underline">
                  {sw('Ripoti', 'Reports')}
                </Link>
              </div>
              <Suspense fallback={<p className="text-sm text-ink-700">{sw('Inapakia chati…', 'Loading chart…')}</p>}>
                <RevenueChart series={desk?.series ?? []} currency={currency} empty={sw('Hakuna kadi zilizouzwa katika siku 14.', 'No cards sold in 14 days.')} />
              </Suspense>
            </Card>
            <Card className="lg:col-span-2">
              <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Mtandaoni sasa', 'Online now')}</h2>
                <Link to="/desk/wateja" className="text-sm font-semibold text-brand-700 hover:underline">
                  {sw('Orodha', 'List')}
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
              {(desk?.online.length ?? 0) === 0 && <Empty>{sw('Hakuna aliye mtandaoni.', 'Nobody is online.')}</Empty>}
            </Card>
          </div>

          <Card className="mt-4">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Uza sasa', 'Sell now')}</h2>
              <Link to="/desk/uza" className="text-sm font-semibold text-brand-700 hover:underline">
                {sw('Vocha', 'Vouchers')}
              </Link>
            </div>
            {nextPack ? (
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-lg font-semibold text-ink-900">{nextPack.plan?.name ?? nextPack.reference}</p>
                  <p className="text-sm text-ink-700">
                    {nextPack.plan?.price_minor != null ? money(nextPack.plan.price_minor, currency) : nextPack.reference}
                    {' · '}
                    {nextPack.printable_count ?? 0} {sw('kadi zimebaki', 'cards left')}
                  </p>
                </div>
                <Link to="/desk/uza" className={primaryBtn}>
                  {sw('Uza kadi', 'Sell cards')}
                </Link>
              </div>
            ) : (
              <Empty>{sw('Bado hujapewa kadi za kuuza kwenye eneo hili. Omba kwa msimamizi.', 'You have not been given cards to sell at this site. Ask the administrator.')}</Empty>
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
      <p className="text-[11px] leading-tight text-ink-700 sm:text-sm">{label}</p>
      <p className="mt-0.5 text-base font-bold leading-tight tracking-tight text-ink-900 sm:mt-1 sm:text-2xl">{value}</p>
      {hint && <p className="mt-0.5 line-clamp-2 text-[11px] leading-snug text-ink-700 sm:mt-1 sm:text-sm">{hint}</p>}
    </Card>
  );

  return to ? <Link to={to}>{body}</Link> : body;
}
