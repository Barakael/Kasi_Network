import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api, openPrintSheet, type Batch } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';

export function BatchesPage() {
  const client = useQueryClient();
  const batches = useQuery({ queryKey: ['batches'], queryFn: api.batches, refetchInterval: 8_000 });
  const plans = useQuery({ queryKey: ['plans'], queryFn: api.plans });
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ plan_id: '', quantity: 24, site_id: '', assigned_agent_id: '', reference: '' });

  const create = useMutation({
    mutationFn: () =>
      api.createBatch({
        plan_id: Number(form.plan_id),
        quantity: Number(form.quantity),
        site_id: form.site_id ? Number(form.site_id) : null,
        assigned_agent_id: form.assigned_agent_id ? Number(form.assigned_agent_id) : null,
        reference: form.reference || null,
      }),
    onSuccess: () => {
      setForm({ ...form, reference: '' });
      void client.invalidateQueries({ queryKey: ['batches'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const disable = useMutation({
    mutationFn: (id: number) => api.disableBatch(id, 'Withdrawn from console'),
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

  return (
    <div>
      <PageHeader
        title="Vouchers"
        subtitle="Each row is a print pack. Tap Print voucher cards to open the codes in a new tab, then use the browser print dialog."
      />
      <ErrorBanner message={error} />
      <Card className="mb-6">
        <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">New print pack</h2>
        <form onSubmit={onSubmit} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <Field label="Bundle">
            <select className={inputClass} value={form.plan_id} onChange={(e) => setForm({ ...form, plan_id: e.target.value })} required>
              <option value="">Select…</option>
              {(plans.data?.data ?? []).map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="How many vouchers">
            <input className={inputClass} type="number" min={1} max={10000} value={form.quantity} onChange={(e) => setForm({ ...form, quantity: Number(e.target.value) })} />
          </Field>
          <Field label="Site">
            <select className={inputClass} value={form.site_id} onChange={(e) => setForm({ ...form, site_id: e.target.value })}>
              <option value="">Any</option>
              {(sites.data?.data ?? []).map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Agent">
            <select className={inputClass} value={form.assigned_agent_id} onChange={(e) => setForm({ ...form, assigned_agent_id: e.target.value })}>
              <option value="">Unassigned</option>
              {(agents.data?.data ?? []).map((agent) => (
                <option key={agent.id} value={agent.id}>
                  {agent.name}
                </option>
              ))}
            </select>
          </Field>
          <div className="flex items-end">
            <button type="submit" className={primaryBtn} disabled={create.isPending}>
              {create.isPending ? 'Creating…' : 'Create pack'}
            </button>
          </div>
        </form>
      </Card>
      <div className="space-y-3">
        {(batches.data?.data ?? []).map((batch) => (
          <BatchRow key={batch.id} batch={batch} onPrint={() => void print(batch)} onWithdraw={() => disable.mutate(batch.id)} />
        ))}
      </div>
      {!batches.data?.data.length && <Empty>{batches.isLoading ? 'Loading print packs…' : 'No print packs yet.'}</Empty>}
    </div>
  );
}

function BatchRow({ batch, onPrint, onWithdraw }: { batch: Batch; onPrint: () => void; onWithdraw: () => void }) {
  const generating = batch.status === 'generating';
  const unused = batch.unused_count;
  const issued = batch.issued_count;

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-semibold tracking-wide text-ink-700 uppercase">Print pack</p>
          <p className="font-semibold text-ink-900">{batch.reference}</p>
          <p className="mt-1 text-sm text-ink-800">
            {batch.quantity} {batch.plan?.name ?? 'bundle'} voucher{batch.quantity === 1 ? '' : 's'}
            {batch.site?.name ? ` · ${batch.site.name}` : ''}
            {batch.assigned_agent?.name ? ` · ${batch.assigned_agent.name}` : ''}
          </p>
          <p className="mt-1 text-xs text-ink-700">
            {generating
              ? `Creating ${batch.quantity} voucher codes…`
              : `${issued ?? batch.quantity} codes in pack · ${unused ?? '—'} still unused · printed ${batch.print_count}×`}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={batch.status === 'ready' ? 'green' : batch.status === 'disabled' ? 'red' : 'amber'}>
            {batch.status_label}
          </Badge>
          {batch.is_printable && (
            <button type="button" className={primaryBtn} onClick={onPrint}>
              Print voucher cards
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
