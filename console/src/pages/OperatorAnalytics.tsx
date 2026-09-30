import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense, useState } from 'react';
import { Link } from 'react-router';
import { api, type Insights } from '../api';
import {
  Badge,
  Callout,
  Delta,
  Empty,
  Meter,
  PageHeader,
  Section,
  Segmented,
  StatCard,
} from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

const RevenueChart = lazy(() => import('./RevenueChart'));

type Period = 'day' | 'week' | 'month';

export function OperatorAnalyticsPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const periodOptions: { value: Period; label: string }[] = [
    { value: 'day', label: sw('Leo', 'Today') },
    { value: 'week', label: sw('Wiki', 'Week') },
    { value: 'month', label: sw('Mwezi', 'Month') },
  ];
  const [period, setPeriod] = useState<Period>('day');
  const insights = useQuery({
    queryKey: ['insights', period],
    queryFn: () => api.insights(period),
    refetchInterval: 30_000,
  });

  const currency = user?.tenant?.currency ?? 'TZS';
  const data = insights.data;

  if (insights.isError) {
    return <p className="text-red-700">{(insights.error as Error).message}</p>;
  }

  return (
    <div>
      <PageHeader
        title={sw('Ripoti', 'Reports')}
        subtitle={`${user?.tenant?.portal_name || user?.tenant?.name || sw('Mwendeshaji', 'Operator')} — ${sw('pesa, wateja mtandaoni, kadi, na kila wakala.', 'money, online customers, cards, and every agent.')}`}
      >
        <Segmented value={period} options={periodOptions} onChange={setPeriod} label={sw('Kipindi', 'Period')} />
      </PageHeader>

      {!data && insights.isLoading ? (
        <Empty>{sw('Inapakia takwimu…', 'Loading figures…')}</Empty>
      ) : (
        data && (
          <div className="space-y-6">
            <Income data={data} currency={currency} period={period} />
            <Customers data={data} />
            <Stock data={data} currency={currency} />
            <Agents data={data} currency={currency} />
            <div className="grid gap-4 lg:grid-cols-2">
              <Sites data={data} currency={currency} />
              <Packages data={data} currency={currency} />
            </div>
          </div>
        )
      )}
    </div>
  );
}

function Income({ data, currency, period }: { data: Insights; currency: string; period: Period }) {
  const { sw } = usePrefs();
  const income = data.income;
  const lipiaShare = income.total_minor > 0 ? Math.round((income.lipia_minor / income.total_minor) * 100) : 0;
  const earlier = period === 'day' ? sw('jana', 'yesterday') : period === 'week' ? sw('wiki iliyopita', 'last week') : sw('mwezi uliopita', 'last month');

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
        <StatCard
          label={period === 'day' ? sw('Mapato leo', 'Income today') : period === 'week' ? sw('Mapato wiki hii', 'Income this week') : sw('Mapato mwezi huu', 'Income this month')}
          value={money(income.total_minor, currency)}
          hint={`${money(income.previous_total_minor, currency)} ${earlier}`}
        >
          <Delta pct={income.change_pct} />
        </StatCard>
        <StatCard
          label="Lipia"
          value={money(income.lipia_minor, currency)}
          hint={`${income.lipia_count} ${sw('oda kwenye portal', 'portal orders')} · ${lipiaShare}% ${sw('ya mapato', 'of income')}`}
          to="/collections"
        />
        <StatCard
          label={sw('Vocha zilizotumika', 'Used vouchers')}
          value={money(income.kadi_minor, currency)}
          hint={`${income.kadi_count} ${sw('kadi ziliingia Wi‑Fi', 'cards joined Wi‑Fi')}`}
          to="/collections"
        />
        <StatCard
          label={sw('Tangu mwanzo', 'Since the start')}
          value={money(income.lifetime_minor, currency)}
          hint={`${sw('Mwezi huu', 'This month')} ${money(income.month_minor, currency)} · ${sw('leo', 'today')} ${money(income.today_minor, currency)}`}
        />
      </div>
      <Section
        title={sw('Siku 14 — Lipia + kadi', '14 days — Lipia + cards')}
        action={
          <Link to="/collections" className="text-sm font-semibold text-brand-700 hover:underline">
            {sw('Makusanyo kwa eneo / wakala', 'Collections by site / agent')}
          </Link>
        }
      >
        <Suspense fallback={<p className="text-sm text-ink-700">{sw('Inapakia chati…', 'Loading chart…')}</p>}>
          <RevenueChart series={data.series} currency={currency} empty={sw('Hakuna mauzo katika dirisha hili.', 'No sales in this window.')} />
        </Suspense>
      </Section>
    </div>
  );
}

function Customers({ data }: { data: Insights }) {
  const { sw } = usePrefs();
  const c = data.customers;
  const offline = Math.max(0, c.total - c.online_now);

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
        <StatCard
          label={sw('Mtandaoni sasa', 'Online now')}
          value={c.online_now}
          hint={`${c.sessions_open} ${sw('session wazi', 'open sessions')}`}
          to="/sessions"
        />
        <StatCard label={sw('Hawapo mtandaoni', 'Not online')} value={offline} hint={sw('Wana namba, hawana session sasa', 'They have a number, no session now')} to="/customers" />
        <StatCard label={sw('Hai', 'Active')} value={c.hai} hint={`${c.kimya} ${sw('kimya — hawana kifurushi', 'quiet — no package')}`} to="/customers" />
        <StatCard
          label={sw('Wateja wapya', 'New customers')}
          value={c.new_in_period}
          hint={`${c.new_today} ${sw('leo', 'today')} · ${c.repeat_buyers} ${sw('wanaorudia', 'returning')}`}
          to="/customers"
        />
      </div>
      <Section
        title={sw('Wateja', 'Customers')}
        action={
          <Link to="/customers" className="text-sm font-semibold text-brand-700 hover:underline">
            {sw('Orodha', 'List')}
          </Link>
        }
      >
        <Meter
          segments={[
            { label: sw('Mtandaoni', 'Online'), value: c.online_now, className: 'bg-emerald-500' },
            { label: sw('Hai bila session', 'Active, no session'), value: Math.max(0, c.hai - c.online_now), className: 'bg-brand-600' },
            { label: sw('Kimya', 'Quiet'), value: c.kimya, className: 'bg-slate-300' },
          ]}
        />
        {c.idle > 0 && (
          <p className="mt-3 text-sm text-ink-700">
            {c.idle} {sw('hawajaonekana kwa siku', 'have not been seen for')} {c.idle_days} {sw('siku', 'days')}.{' '}
            <Link to="/settings" className="font-semibold text-brand-700 hover:underline">
              {sw('Tuma tangazo', 'Send a notice')}
            </Link>
          </p>
        )}
      </Section>
    </div>
  );
}

function Stock({ data, currency }: { data: Insights; currency: string }) {
  const { sw } = usePrefs();
  const s = data.stock;
  const risk = s.expiring_soon > 0 || s.dead_stock > 0;

  return (
    <div className="space-y-4">
      {risk && (
        <Callout tone="amber">
          {s.expiring_soon > 0 && (
            <>
              {sw('Kadi', 'Cards')} {s.expiring_soon} ({money(s.expiring_soon_value_minor, currency)}) {sw('zinaisha ndani ya siku', 'expire within')} {s.soon_days} {sw('siku — uza kwanza.', 'days — sell these first.')}{' '}
            </>
          )}
          {s.dead_stock > 0 && (
            <>
              {sw('Kadi', 'Cards')} {s.dead_stock} ({money(s.dead_stock_value_minor, currency)}) {sw('zimeshapitwa na muda bila kuuzwa.', 'expired on the shelf unsold.')}
            </>
          )}
        </Callout>
      )}
      <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
        <StatCard label={sw('Hazijauzwa', 'Unsold')} value={s.unused} hint={`${s.in_office} ${sw('ofisini', 'in the office')} · ${s.at_counter} ${sw('kwa mawakala', 'with agents')}`} to="/batches" />
        <StatCard label={sw('Zinatumika', 'In use')} value={s.active} hint={sw('Wateja wako ndani ya muda', 'Customers are still within time')} />
        <StatCard label={sw('Zimeisha muda', 'Expired')} value={s.expired} hint={s.time_up > 0 ? `${s.time_up} ${sw('muda umeisha sasa hivi', 'just ran out')}` : sw('Vocha zilizopitwa na muda', 'Vouchers past their time')} />
        <StatCard label={sw('Zimetumika zote', 'Fully used')} value={s.exhausted} hint={`${s.disabled} ${sw('zilizozuiwa', 'blocked')}`} />
      </div>
      <Section
        title={sw('Hali ya kadi', 'Card status')}
        action={
          <Link to="/batches" className="text-sm font-semibold text-brand-700 hover:underline">
            {sw('Vocha', 'Vouchers')}
          </Link>
        }
      >
        <Meter
          segments={[
            { label: sw('Hazijauzwa', 'Unsold'), value: s.unused, className: 'bg-brand-600' },
            { label: sw('Zinatumika', 'In use'), value: s.active, className: 'bg-emerald-500' },
            { label: sw('Zimetumika', 'Used'), value: s.exhausted, className: 'bg-slate-400' },
            { label: sw('Muda umeisha', 'Expired'), value: s.expired, className: 'bg-amber-400' },
            { label: sw('Zimezuiwa', 'Blocked'), value: s.disabled, className: 'bg-slate-300' },
          ]}
        />
        <p className="mt-3 text-sm text-ink-700">
          {sw('Kadi', 'Cards')} {s.at_counter} ({money(s.at_counter_value_minor, currency)}) {sw('zimechapishwa na mawakala lakini hazijaingia Wi‑Fi bado.', 'were printed by agents and have not joined Wi‑Fi yet.')}
        </p>
      </Section>
    </div>
  );
}

function Agents({ data, currency }: { data: Insights; currency: string }) {
  const { sw } = usePrefs();
  const rows = data.agents;

  return (
    <Section
      title={sw('Mapato ya mawakala', 'Agent income')}
      action={
        <Link to="/sites" className="text-sm font-semibold text-brand-700 hover:underline">
          {sw('Mawakala', 'Agents')}
        </Link>
      }
    >
      {rows.length === 0 ? (
        <Empty>
          {sw('Hakuna wakala bado.', 'No agents yet.')}{' '}
          <Link to="/sites" className="font-semibold text-brand-700 hover:underline">
            {sw('Ongeza wakala', 'Add an agent')}
          </Link>
        </Empty>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="console-table">
              <thead>
                <tr>
                  <th>{sw('Wakala', 'Agent')}</th>
                  <th>{sw('Eneo', 'Site')}</th>
                  <th className="text-right">{sw('Pesa mkononi', 'Cash in hand')}</th>
                  <th className="text-right">{sw('Imefika mtandaoni', 'Reached Wi‑Fi')}</th>
                  <th className="text-right">{sw('Stock', 'Stock')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((agent) => (
                  <tr key={agent.id}>
                    <td className="font-semibold text-ink-900">{agent.name}</td>
                    <td className="text-ink-700">{agent.sites.join(', ') || sw('Hakuna eneo', 'No site')}</td>
                    <td className="text-right">
                      <span className="font-semibold text-ink-900">{money(agent.sold_minor, currency)}</span>
                      <span className="block text-xs text-ink-700">{agent.sold_count} {sw('kadi', 'cards')}</span>
                    </td>
                    <td className="text-right">
                      <span className="text-ink-900">{money(agent.delivered_minor, currency)}</span>
                      <span className="block text-xs text-ink-700">{agent.delivered_count} {sw('kadi', 'cards')}</span>
                    </td>
                    <td className="text-right">
                      <Badge tone={agent.stock > 0 ? 'green' : 'amber'}>{agent.stock}</Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="mt-3 text-sm text-ink-700">
            <strong>{sw('Pesa mkononi', 'Cash in hand')}</strong> {sw('ni kadi alizochapisha na kuuza kipindi hiki.', 'is cards they printed and sold this period.')} <strong>{sw('Imefika mtandaoni', 'Reached Wi‑Fi')}</strong> {sw('ni zilizotumika kwenye Wi‑Fi — hizo ndizo zinazoingia makusanyo.', 'is cards used on Wi‑Fi — those are what land in collections.')}
          </p>
        </>
      )}
    </Section>
  );
}

function Sites({ data, currency }: { data: Insights; currency: string }) {
  const { sw } = usePrefs();
  const rows = data.sites;

  return (
    <Section
      title={sw('Maeneo', 'Sites')}
      action={
        <Link to="/sites" className="text-sm font-semibold text-brand-700 hover:underline">
          {sw('Maeneo', 'Sites')}
        </Link>
      }
    >
      {rows.length === 0 ? (
        <Empty>
          {sw('Hakuna eneo bado.', 'No site yet.')}{' '}
          <Link to="/sites" className="font-semibold text-brand-700 hover:underline">
            {sw('Ongeza eneo', 'Add a site')}
          </Link>
        </Empty>
      ) : (
        <ul className="space-y-3">
          {rows.map((site) => (
            <li key={site.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 last:border-none last:pb-0">
              <div className="min-w-0">
                <p className="font-semibold text-ink-900">{site.name}</p>
                <p className="text-sm text-ink-700">
                  {site.online} {sw('mtandaoni', 'online')} · {sw('kadi', 'cards')} {site.cards_left} · {site.routers} {sw('vifaa', 'devices')}
                </p>
              </div>
              <div className="text-right">
                <p className="font-semibold text-ink-900">{money(site.total_minor, currency)}</p>
                {site.router_quiet ? (
                  <Badge tone="amber">{sw('Router kimya', 'Router quiet')}</Badge>
                ) : (
                  <p className="text-xs text-ink-700">
                    Lipia {money(site.lipia_minor, currency)}
                  </p>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </Section>
  );
}

function Packages({ data, currency }: { data: Insights; currency: string }) {
  const { sw } = usePrefs();
  const rows = data.plans;
  const top = rows[0]?.total_minor ?? 0;

  return (
    <Section
      title={sw('Vifurushi vinavyouzwa', 'Packages selling')}
      action={
        <Link to="/plans" className="text-sm font-semibold text-brand-700 hover:underline">
          {sw('Vifurushi', 'Packages')}
        </Link>
      }
    >
      {rows.length === 0 ? (
        <Empty>{sw('Hakuna mauzo kwenye kipindi hiki.', 'No sales in this period.')}</Empty>
      ) : (
        <ul className="space-y-3">
          {rows.slice(0, 6).map((plan) => (
            <li key={plan.id}>
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="font-semibold text-ink-900">{plan.name}</p>
                <p className="font-semibold text-ink-900">{money(plan.total_minor, currency)}</p>
              </div>
              <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                <span
                  className="block h-full rounded-full bg-brand-600"
                  style={{ width: `${top > 0 ? Math.max(4, (plan.total_minor / top) * 100) : 0}%` }}
                />
              </div>
              <p className="mt-1 text-xs text-ink-700">
                {plan.kadi_count} {sw('kadi', 'cards')} · {plan.lipia_count} Lipia
              </p>
            </li>
          ))}
        </ul>
      )}
    </Section>
  );
}
