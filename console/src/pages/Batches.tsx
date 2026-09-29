import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api, openPrintSheet, type Batch, type User } from '../api';
import { Badge, Callout, Card, Empty, ErrorBanner, Field, Guide, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';

function agentOnSite(agent: User, siteId: string) {
  if (!siteId) {
    return true;
  }
  const id = Number(siteId);
  return (agent.sites ?? []).some((site) => site.id === id) || (agent.site_ids ?? []).includes(id);
}

export function BatchesPage() {
  const client = useQueryClient();
  const batches = useQuery({ queryKey: ['batches'], queryFn: api.batches, refetchInterval: 8_000 });
  const plans = useQuery({ queryKey: ['plans'], queryFn: api.plans });
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ plan_id: '', quantity: 24, site_id: '', assigned_agent_id: '', reference: '' });
  const [filter, setFilter] = useState<'all' | 'unassigned'>('all');

  const siteAgents = useMemo(
    () => (agents.data?.data ?? []).filter((agent) => agentOnSite(agent, form.site_id)),
    [agents.data?.data, form.site_id],
  );

  const create = useMutation({
    mutationFn: () =>
      api.createBatch({
        plan_id: Number(form.plan_id),
        quantity: Number(form.quantity),
        site_id: Number(form.site_id),
        assigned_agent_id: form.assigned_agent_id ? Number(form.assigned_agent_id) : null,
        reference: form.reference || null,
      }),
    onSuccess: () => {
      setForm({ ...form, reference: '', assigned_agent_id: '' });
      void client.invalidateQueries({ queryKey: ['batches'] });
      void client.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const disable = useMutation({
    mutationFn: (id: number) => api.disableBatch(id, 'Withdrawn from console'),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: ['batches'] });
      void client.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const assign = useMutation({
    mutationFn: ({ id, assigned_agent_id }: { id: number; assigned_agent_id: number | null }) =>
      api.updateBatch(id, { assigned_agent_id }),
    onSuccess: () => void client.invalidateQueries({ queryKey: ['batches'] }),
    onError: (err: Error) => setError(err.message),
  });

  async function print(batch: Batch) {
    setError(null);
    try {
      await openPrintSheet(batch.id, batch.printable_count);
    } catch (err) {
      setError((err as Error).message);
    }
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    create.mutate();
  }

  const planCount = plans.data?.data.length ?? 0;
  const siteCount = sites.data?.data.length ?? 0;
  const agentCount = agents.data?.data.length ?? 0;
  const rows = [...(batches.data?.data ?? [])].sort((a, b) => Number(Boolean(a.assigned_agent)) - Number(Boolean(b.assigned_agent)));
  const visible = filter === 'unassigned' ? rows.filter((row) => !row.assigned_agent && row.status !== 'disabled') : rows;
  const unassigned = rows.filter((row) => !row.assigned_agent && row.status !== 'disabled').length;
  const ready = planCount > 0 && siteCount > 0;

  return (
    <div>
      <PageHeader
        title="Packs"
        subtitle="Tengeneza kadi, chagua site, kisha mpe wakala. Yeye anauza kwenye desk — pesa inaingia till yake anapochapisha."
      />
      <ErrorBanner message={error} />
      {plans.isSuccess && planCount === 0 && (
        <Guide title="Hakuna package" body="Bei za kadi zinatoka kwenye Packages. Weka bundle kabla ya kutengeneza pack." to="/plans" cta="Packages" />
      )}
      {sites.isSuccess && siteCount === 0 && (
        <Guide title="Hakuna site" body="Pack inahitaji shop. Weka SSID kwanza, kisha rudi hapa." to="/sites" cta="Add site" />
      )}
      {ready && agentCount === 0 && (
        <Guide
          title="Hakuna wakala"
          body="Unaweza kutengeneza pack sasa, lakini desk haiwezi kuuza hadi umwalike wakala na umpe pack hii."
          to="/agents"
          cta="Invite agent"
        />
      )}
      {unassigned > 0 && (
        <Callout tone="brand">
          {unassigned} pack bado hazijapelekwa counter.{' '}
          <button type="button" className="font-semibold underline" onClick={() => setFilter('unassigned')}>
            Onyesha zisizo na wakala
          </button>
        </Callout>
      )}
      {ready && (
        <Card className="mb-6">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Pack mpya</h2>
          <form onSubmit={onSubmit} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Field label="Package">
              <select className={inputClass} value={form.plan_id} onChange={(e) => setForm({ ...form, plan_id: e.target.value })} required>
                <option value="">Chagua bundle…</option>
                {(plans.data?.data ?? []).map((plan) => (
                  <option key={plan.id} value={plan.id}>
                    {plan.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Idadi ya kadi">
              <input
                className={inputClass}
                type="number"
                min={1}
                max={10000}
                value={form.quantity}
                onChange={(e) => setForm({ ...form, quantity: Number(e.target.value) })}
              />
            </Field>
            <Field label="Site">
              <select
                className={inputClass}
                value={form.site_id}
                onChange={(e) => setForm({ ...form, site_id: e.target.value, assigned_agent_id: '' })}
                required
              >
                <option value="">Chagua site…</option>
                {(sites.data?.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Wakala">
              <select
                className={inputClass}
                value={form.assigned_agent_id}
                onChange={(e) => setForm({ ...form, assigned_agent_id: e.target.value })}
                disabled={!form.site_id}
              >
                <option value="">Weka baadaye</option>
                {siteAgents.map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Kumbukumbu (si lazima)">
              <input
                className={inputClass}
                value={form.reference}
                onChange={(e) => setForm({ ...form, reference: e.target.value })}
                placeholder="Kiosk wiki 39"
              />
            </Field>
            <div className="flex items-end">
              <button type="submit" className={primaryBtn} disabled={create.isPending || !form.plan_id || !form.site_id}>
                {create.isPending ? 'Inatengeneza…' : 'Tengeneza pack'}
              </button>
            </div>
          </form>
          {form.site_id && siteAgents.length === 0 && agentCount > 0 && (
            <p className="mt-3 text-sm text-ink-700">
              Hakuna wakala kwenye site hii.{' '}
              <Link to="/agents" className="font-semibold text-brand-700 hover:underline">
                Pin wakala
              </Link>
            </p>
          )}
        </Card>
      )}
      {rows.length > 0 && (
        <div className="mb-3 flex flex-wrap gap-2">
          <button type="button" className={filter === 'all' ? primaryBtn : secondaryBtn} onClick={() => setFilter('all')}>
            Zote
          </button>
          <button type="button" className={filter === 'unassigned' ? primaryBtn : secondaryBtn} onClick={() => setFilter('unassigned')}>
            Bila wakala ({unassigned})
          </button>
        </div>
      )}
      <div className="space-y-3">
        {visible.map((batch) => (
          <BatchRow
            key={batch.id}
            batch={batch}
            agents={agents.data?.data ?? []}
            onPrint={() => void print(batch)}
            onWithdraw={() => disable.mutate(batch.id)}
            onAssign={(assigned_agent_id) => {
              setError(null);
              assign.mutate({ id: batch.id, assigned_agent_id });
            }}
          />
        ))}
      </div>
      {!batches.data?.data.length && <Empty>{batches.isLoading ? 'Inapakia packs…' : 'Hakuna pack bado.'}</Empty>}
      {batches.data?.data.length && visible.length === 0 && <Empty>Hakuna pack bila wakala.</Empty>}
    </div>
  );
}

function BatchRow({
  batch,
  agents,
  onPrint,
  onWithdraw,
  onAssign,
}: {
  batch: Batch;
  agents: User[];
  onPrint: () => void;
  onWithdraw: () => void;
  onAssign: (id: number | null) => void;
}) {
  const generating = batch.status === 'generating';
  const unused = batch.unused_count;
  const issued = batch.issued_count;
  const siteAgents = agents.filter((agent) => agentOnSite(agent, String(batch.site?.id ?? '')));

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-semibold tracking-wide text-ink-700 uppercase">Print pack</p>
          <p className="font-semibold text-ink-900">{batch.reference}</p>
          <p className="mt-1 text-sm text-ink-800">
            {batch.quantity} {batch.plan?.name ?? 'bundle'}
            {batch.site?.name ? ` · ${batch.site.name}` : ' · hakuna site'}
            {batch.assigned_agent?.name ? ` · ${batch.assigned_agent.name}` : ' · bado haijapelekwa'}
          </p>
          <p className="mt-1 text-xs text-ink-700">
            {generating
              ? `Inatengeneza nambari ${batch.quantity}…`
              : `${issued ?? batch.quantity} kadi · ${unused ?? '—'} hazijauzwa · chapishwa ${batch.print_count}×`}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={batch.status === 'ready' ? 'green' : batch.status === 'disabled' ? 'red' : 'amber'}>
            {batch.status_label}
          </Badge>
          {batch.status !== 'disabled' && (
            <select
              className={`${inputClass} w-48`}
              aria-label="Mpe wakala"
              value={batch.assigned_agent?.id ?? ''}
              onChange={(e) => onAssign(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">Bila wakala</option>
              {siteAgents.map((agent) => (
                <option key={agent.id} value={agent.id}>
                  {agent.name}
                </option>
              ))}
            </select>
          )}
          {batch.is_printable && (
            <button type="button" className={secondaryBtn} onClick={onPrint}>
              Chapisha ofisini
            </button>
          )}
          {batch.status !== 'disabled' && (
            <button type="button" className={secondaryBtn} onClick={onWithdraw}>
              Withdraw
            </button>
          )}
        </div>
      </div>
    </Card>
  );
}
