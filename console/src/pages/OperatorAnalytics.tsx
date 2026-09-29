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

const RevenueChart = lazy(() => import('./RevenueChart'));

type Period = 'day' | 'week' | 'month';

const periodOptions: { value: Period; label: string }[] = [
  { value: 'day', label: 'Leo' },
  { value: 'week', label: 'Wiki' },
  { value: 'month', label: 'Mwezi' },
];

const periodWord: Record<Period, string> = {
  day: 'jana',
  week: 'wiki iliyopita',
  month: 'mwezi uliopita',
};

export function OperatorAnalyticsPage() {
  const { user } = useAuth();
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
        title="Analytics"
        subtitle={`${user?.tenant?.portal_name || user?.tenant?.name || 'Operator'} — pesa, wateja mtandaoni, kadi, na kila wakala.`}
      >
        <Segmented value={period} options={periodOptions} onChange={setPeriod} label="Kipindi" />
      </PageHeader>

      {!data && insights.isLoading ? (
        <Empty>Inapakia takwimu…</Empty>
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
  const income = data.income;
  const lipiaShare = income.total_minor > 0 ? Math.round((income.lipia_minor / income.total_minor) * 100) : 0;

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label={period === 'day' ? 'Mapato leo' : period === 'week' ? 'Mapato wiki hii' : 'Mapato mwezi huu'}
          value={money(income.total_minor, currency)}
          hint={`${money(income.previous_total_minor, currency)} ${periodWord[period]}`}
        >
          <Delta pct={income.change_pct} />
        </StatCard>
        <StatCard
          label="Lipia"
          value={money(income.lipia_minor, currency)}
          hint={`${income.lipia_count} oda kwenye portal · ${lipiaShare}% ya mapato`}
          to="/collections"
        />
        <StatCard
          label="Kadi zilizotumika"
          value={money(income.kadi_minor, currency)}
          hint={`${income.kadi_count} kadi ziliingia Wi‑Fi`}
          to="/collections"
        />
        <StatCard
          label="Tangu mwanzo"
          value={money(income.lifetime_minor, currency)}
          hint={`Mwezi huu ${money(income.month_minor, currency)} · leo ${money(income.today_minor, currency)}`}
        />
      </div>
      <Section
        title="Siku 14 — Lipia + kadi"
        action={
          <Link to="/collections" className="text-sm font-semibold text-brand-700 hover:underline">
            Collections kwa site / wakala
          </Link>
        }
      >
        <Suspense fallback={<p className="text-sm text-ink-700">Inapakia chati…</p>}>
          <RevenueChart series={data.series} currency={currency} empty="Hakuna mauzo katika dirisha hili." />
        </Suspense>
      </Section>
    </div>
  );
}

function Customers({ data }: { data: Insights }) {
  const c = data.customers;
  const offline = Math.max(0, c.total - c.online_now);

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Mtandaoni sasa"
          value={c.online_now}
          hint={`${c.sessions_open} session wazi`}
          to="/sessions"
        />
        <StatCard label="Hawapo mtandaoni" value={offline} hint="Wana namba, hawana session sasa" to="/customers" />
        <StatCard label="Hai" value={c.hai} hint={`${c.kimya} kimya — hawana bundle`} to="/customers" />
        <StatCard
          label="Wateja wapya"
          value={c.new_in_period}
          hint={`${c.new_today} leo · ${c.repeat_buyers} wanaorudia`}
          to="/customers"
        />
      </div>
      <Section
        title="Wateja"
        action={
          <Link to="/customers" className="text-sm font-semibold text-brand-700 hover:underline">
            Orodha
          </Link>
        }
      >
        <Meter
          segments={[
            { label: 'Mtandaoni', value: c.online_now, className: 'bg-emerald-500' },
            { label: 'Hai bila session', value: Math.max(0, c.hai - c.online_now), className: 'bg-brand-600' },
            { label: 'Kimya', value: c.kimya, className: 'bg-slate-300' },
          ]}
        />
        {c.idle > 0 && (
          <p className="mt-3 text-sm text-ink-700">
            {c.idle} hawajaonekana kwa siku {c.idle_days}.{' '}
            <Link to="/campaigns" className="font-semibold text-brand-700 hover:underline">
              Tuma notice
            </Link>
          </p>
        )}
      </Section>
    </div>
  );
}

function Stock({ data, currency }: { data: Insights; currency: string }) {
  const s = data.stock;
  const risk = s.expiring_soon > 0 || s.dead_stock > 0;

  return (
    <div className="space-y-4">
      {risk && (
        <Callout tone="amber">
          {s.expiring_soon > 0 && (
            <>
              Kadi {s.expiring_soon} ({money(s.expiring_soon_value_minor, currency)}) zinaisha ndani ya siku {s.soon_days} — uza kwanza.{' '}
            </>
          )}
          {s.dead_stock > 0 && (
            <>
              Kadi {s.dead_stock} ({money(s.dead_stock_value_minor, currency)}) zimeshapitwa na muda bila kuuzwa.
            </>
          )}
        </Callout>
      )}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Hazijauzwa" value={s.unused} hint={`${s.in_office} ofisini · ${s.at_counter} kwa mawakala`} to="/batches" />
        <StatCard label="Zinatumika" value={s.active} hint="Wateja wako ndani ya muda" />
        <StatCard label="Zimeisha muda" value={s.expired} hint={s.time_up > 0 ? `${s.time_up} muda umeisha sasa hivi` : 'Vocha zilizopitwa na muda'} />
        <StatCard label="Zimetumika zote" value={s.exhausted} hint={`${s.disabled} zilizozuiwa`} />
      </div>
      <Section
        title="Hali ya kadi"
        action={
          <Link to="/batches" className="text-sm font-semibold text-brand-700 hover:underline">
            Packs
          </Link>
        }
      >
        <Meter
          segments={[
            { label: 'Hazijauzwa', value: s.unused, className: 'bg-brand-600' },
            { label: 'Zinatumika', value: s.active, className: 'bg-emerald-500' },
            { label: 'Zimetumika', value: s.exhausted, className: 'bg-slate-400' },
            { label: 'Muda umeisha', value: s.expired, className: 'bg-amber-400' },
            { label: 'Zimezuiwa', value: s.disabled, className: 'bg-slate-300' },
          ]}
        />
        <p className="mt-3 text-sm text-ink-700">
          Kadi {s.at_counter} ({money(s.at_counter_value_minor, currency)}) zimechapishwa na mawakala lakini hazijaingia Wi‑Fi bado.
        </p>
      </Section>
    </div>
  );
}

function Agents({ data, currency }: { data: Insights; currency: string }) {
  const rows = data.agents;

  return (
    <Section
      title="Mapato ya mawakala"
      action={
        <Link to="/agents" className="text-sm font-semibold text-brand-700 hover:underline">
          Mawakala
        </Link>
      }
    >
      {rows.length === 0 ? (
        <Empty>
          Hakuna wakala bado.{' '}
          <Link to="/agents" className="font-semibold text-brand-700 hover:underline">
            Mwalike wakala
          </Link>
        </Empty>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="console-table">
              <thead>
                <tr>
                  <th>Wakala</th>
                  <th>Site</th>
                  <th className="text-right">Pesa mkononi</th>
                  <th className="text-right">Imefika mtandaoni</th>
                  <th className="text-right">Stock</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((agent) => (
                  <tr key={agent.id}>
                    <td className="font-semibold text-ink-900">{agent.name}</td>
                    <td className="text-ink-700">{agent.sites.join(', ') || 'Hakuna site'}</td>
                    <td className="text-right">
                      <span className="font-semibold text-ink-900">{money(agent.sold_minor, currency)}</span>
                      <span className="block text-xs text-ink-700">{agent.sold_count} kadi</span>
                    </td>
                    <td className="text-right">
                      <span className="text-ink-900">{money(agent.delivered_minor, currency)}</span>
                      <span className="block text-xs text-ink-700">{agent.delivered_count} kadi</span>
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
            <strong>Pesa mkononi</strong> ni kadi alizochapisha na kuuza kipindi hiki. <strong>Imefika mtandaoni</strong> ni
            zilizotumika kwenye Wi‑Fi — hizo ndizo zinazoingia Collections zako.
          </p>
        </>
      )}
    </Section>
  );
}

function Sites({ data, currency }: { data: Insights; currency: string }) {
  const rows = data.sites;

  return (
    <Section
      title="Sites"
      action={
        <Link to="/sites" className="text-sm font-semibold text-brand-700 hover:underline">
          Sites
        </Link>
      }
    >
      {rows.length === 0 ? (
        <Empty>
          Hakuna site bado.{' '}
          <Link to="/sites" className="font-semibold text-brand-700 hover:underline">
            Weka shop
          </Link>
        </Empty>
      ) : (
        <ul className="space-y-3">
          {rows.map((site) => (
            <li key={site.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 last:border-none last:pb-0">
              <div className="min-w-0">
                <p className="font-semibold text-ink-900">{site.name}</p>
                <p className="text-sm text-ink-700">
                  {site.online} mtandaoni · kadi {site.cards_left} · {site.routers} router
                </p>
              </div>
              <div className="text-right">
                <p className="font-semibold text-ink-900">{money(site.total_minor, currency)}</p>
                {site.router_quiet ? (
                  <Badge tone="amber">Router kimya</Badge>
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
  const rows = data.plans;
  const top = rows[0]?.total_minor ?? 0;

  return (
    <Section
      title="Packages zinazouzwa"
      action={
        <Link to="/plans" className="text-sm font-semibold text-brand-700 hover:underline">
          Packages
        </Link>
      }
    >
      {rows.length === 0 ? (
        <Empty>Hakuna mauzo kwenye kipindi hiki.</Empty>
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
                {plan.kadi_count} kadi · {plan.lipia_count} Lipia
              </p>
            </li>
          ))}
        </ul>
      )}
    </Section>
  );
}
