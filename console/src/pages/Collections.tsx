import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense, useState } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Callout, Card, Delta, PageHeader, Segmented, inputClass } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

const RevenueChart = lazy(() => import('./RevenueChart'));

type Period = 'day' | 'week' | 'month';

export function CollectionsPage() {
  const { user } = useAuth();
  const [period, setPeriod] = useState<Period>('day');
  const [siteId, setSiteId] = useState('');
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const [agentId, setAgentId] = useState('');
  const data = useQuery({
    queryKey: ['collections', period, siteId, agentId],
    queryFn: () => api.collections(period, siteId ? Number(siteId) : undefined, agentId ? Number(agentId) : undefined),
  });
  const currency = user?.tenant?.currency ?? 'TZS';
  const row = data.data;
  const logo = user?.tenant?.logo_url;

  return (
    <div>
      <PageHeader
        title="Collections"
        subtitle={`${user?.tenant?.portal_name || user?.tenant?.name || 'Operator'} — internet iliyotolewa, si pesa mkononi ya wakala.`}
      />
      {logo && <img src={logo} alt="" className="mb-4 h-12 object-contain" />}
      <Callout>
        <strong>Lipia</strong> ni malipo kwenye portal.{' '}
        <strong>Kadi</strong> ni vocha zilizotumika kwenye Wi‑Fi. Wakala anaona pesa mkononi kwenye desk yake wakati anauza
        (chapisha) — hiyo si kwenye ukurasa huu.
      </Callout>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <Segmented
          value={period}
          onChange={setPeriod}
          label="Kipindi"
          options={[
            { value: 'day', label: 'Leo' },
            { value: 'week', label: 'Wiki' },
            { value: 'month', label: 'Mwezi' },
          ]}
        />
        <select className={`${inputClass} max-w-48`} value={siteId} onChange={(e) => setSiteId(e.target.value)}>
          <option value="">Sites zote</option>
          {(sites.data?.data ?? []).map((site) => (
            <option key={site.id} value={site.id}>
              {site.name}
            </option>
          ))}
        </select>
        <select className={`${inputClass} max-w-48`} value={agentId} onChange={(e) => setAgentId(e.target.value)}>
          <option value="">Mawakala wote</option>
          {(agents.data?.data ?? []).map((agent) => (
            <option key={agent.id} value={agent.id}>
              {agent.name}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <Card>
          <p className="text-sm text-ink-700">Lipia</p>
          <p className="mt-1 text-2xl font-bold">{row ? money(row.lipia_minor, currency) : '—'}</p>
          <p className="text-sm text-ink-700">{row?.lipia_count ?? 0} orders kwenye portal</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">Kadi zilizotumika</p>
          <p className="mt-1 text-2xl font-bold">{row ? money(row.kadi_minor, currency) : '—'}</p>
          <p className="text-sm text-ink-700">{row?.kadi_count ?? 0} ziliingia Wi‑Fi</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">Jumla</p>
          <p className="mt-1 text-2xl font-bold">{row ? money(row.total_minor, currency) : '—'}</p>
          <p className="text-sm text-ink-700">
            Kipindi kilichopita: {row ? money(row.previous_total_minor, currency) : '—'}
          </p>
          {row && (
            <p className="mt-2">
              <Delta pct={row.previous_total_minor === 0 ? null : Math.round(((row.total_minor - row.previous_total_minor) / row.previous_total_minor) * 100)} />
            </p>
          )}
        </Card>
      </div>
      <Card className="mt-6">
        <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">
          {period === 'day' ? 'Leo' : period === 'week' ? 'Wiki hii' : 'Mwezi huu'}
        </h2>
        <Suspense fallback={<p className="text-sm text-ink-700">Inapakia chati…</p>}>
          <RevenueChart series={row?.series ?? []} currency={currency} />
        </Suspense>
      </Card>
      <p className="mt-4 text-sm text-ink-700">
        Bili ya Kasi (unachodaiwa platform) iko kwenye{' '}
        <Link to="/billing" className="font-semibold text-brand-700 hover:underline">
          Billing
        </Link>
        , si hapa.
      </p>
    </div>
  );
}
