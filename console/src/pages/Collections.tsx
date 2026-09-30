import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense, useState } from 'react';
import { api, type Collections } from '../api';
import { PageHeader, inputClass } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

const RevenueChart = lazy(() => import('./RevenueChart'));

type Period = 'day' | 'week' | 'month';

export function CollectionsPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const periods: { key: Period; label: string }[] = [
    { key: 'day', label: sw('Leo', 'Today') },
    { key: 'week', label: sw('Wiki', 'Week') },
    { key: 'month', label: sw('Mwezi', 'Month') },
  ];
  const [siteId, setSiteId] = useState('');
  const [agentId, setAgentId] = useState('');
  const [focus, setFocus] = useState<Period>('day');
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const currency = user?.tenant?.currency ?? 'TZS';
  const site = siteId ? Number(siteId) : undefined;
  const agent = agentId ? Number(agentId) : undefined;

  const day = useQuery({ queryKey: ['collections', 'day', siteId, agentId], queryFn: () => api.collections('day', site, agent) });
  const week = useQuery({ queryKey: ['collections', 'week', siteId, agentId], queryFn: () => api.collections('week', site, agent) });
  const month = useQuery({ queryKey: ['collections', 'month', siteId, agentId], queryFn: () => api.collections('month', site, agent) });
  const byPeriod: Record<Period, Collections | undefined> = { day: day.data, week: week.data, month: month.data };

  return (
    <div>
      <PageHeader title={sw('Makusanyo', 'Collections')} />
      <div className="mb-4 flex flex-wrap gap-2">
        <select className={`${inputClass} max-w-48`} value={siteId} onChange={(e) => setSiteId(e.target.value)}>
          <option value="">{sw('Maeneo', 'Sites')}</option>
          {(sites.data?.data ?? []).map((row) => (
            <option key={row.id} value={row.id}>
              {row.name}
            </option>
          ))}
        </select>
        <select className={`${inputClass} max-w-48`} value={agentId} onChange={(e) => setAgentId(e.target.value)}>
          <option value="">{sw('Mawakala', 'Agents')}</option>
          {(agents.data?.data ?? []).map((row) => (
            <option key={row.id} value={row.id}>
              {row.name}
            </option>
          ))}
        </select>
      </div>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
        {periods.map((period) => {
          const row = byPeriod[period.key];
          return (
            <button
              key={period.key}
              type="button"
              onClick={() => setFocus(period.key)}
              className={`rounded-2xl border p-4 text-left shadow-sm ${
                focus === period.key ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white'
              }`}
            >
              <p className="text-xs font-semibold tracking-wide text-ink-700 uppercase">{period.label}</p>
              <p className="mt-1 text-2xl font-bold text-ink-900">{row ? money(row.total_minor, currency) : '—'}</p>
              <p className="mt-2 text-sm text-ink-700">
                {sw('Vocha', 'Vouchers')} {row ? money(row.kadi_minor, currency) : '—'}
              </p>
              <p className="text-sm text-ink-700">
                {sw('Malipo ya simu', 'Mobile money')} {row ? money(row.lipia_minor, currency) : '—'}
              </p>
            </button>
          );
        })}
      </div>
      <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="mb-3 text-sm font-semibold text-ink-700">{periods.find((period) => period.key === focus)?.label}</h2>
        <Suspense fallback={<p className="text-sm text-ink-700">…</p>}>
          <RevenueChart series={byPeriod[focus]?.series ?? []} currency={currency} />
        </Suspense>
      </section>
    </div>
  );
}
