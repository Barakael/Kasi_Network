import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api } from '../api';
import { Card, PageHeader, inputClass } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

export function CollectionsPage() {
  const { user } = useAuth();
  const [period, setPeriod] = useState('day');
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
      <PageHeader title="Collections" subtitle={`${user?.tenant?.portal_name || user?.tenant?.name || 'This operator'} — Lipia vs kadi.`} />
      {logo && <img src={logo} alt="" className="mb-4 h-12 object-contain" />}
      <div className="mb-4 flex flex-wrap gap-2">
        {['day', 'week', 'month'].map((p) => (
          <button
            key={p}
            type="button"
            className={`rounded-lg px-3 py-2 text-sm font-semibold ${period === p ? 'bg-brand-600 text-white' : 'bg-white text-ink-800 border border-slate-300'}`}
            onClick={() => setPeriod(p)}
          >
            {p === 'day' ? 'Leo' : p === 'week' ? 'Wiki' : 'Mwezi'}
          </button>
        ))}
        <select className={inputClass} value={siteId} onChange={(e) => setSiteId(e.target.value)}>
          <option value="">All sites</option>
          {(sites.data?.data ?? []).map((site) => (
            <option key={site.id} value={site.id}>
              {site.name}
            </option>
          ))}
        </select>
        <select className={inputClass} value={agentId} onChange={(e) => setAgentId(e.target.value)}>
          <option value="">All agents</option>
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
          <p className="text-sm text-ink-700">{row?.lipia_count ?? 0} orders</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">Kadi</p>
          <p className="mt-1 text-2xl font-bold">{row ? money(row.kadi_minor, currency) : '—'}</p>
          <p className="text-sm text-ink-700">{row?.kadi_count ?? 0} cards</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">Total</p>
          <p className="mt-1 text-2xl font-bold">{row ? money(row.total_minor, currency) : '—'}</p>
          <p className="text-sm text-ink-700">
            Last {period}: {row ? money(row.previous_total_minor, currency) : '—'}
          </p>
        </Card>
      </div>
    </div>
  );
}
