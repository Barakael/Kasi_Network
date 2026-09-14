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
      await openPrintSheet(batch.id);
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
      <PageHeader title="Voucher batches" subtitle="Issue a print run, then open the sheet in a new tab." />
      <ErrorBanner message={error} />
      <Card className="mb-4">
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
          <Field label="Quantity">
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
              Issue batch
            </button>
          </div>
        </form>
      </Card>
      <div className="space-y-3">
        {(batches.data?.data ?? []).map((batch) => (
          <Card key={batch.id}>
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold">{batch.reference}</p>
                <p className="text-sm text-ink-700">
                  {batch.plan?.name} · {batch.quantity} cards
                  {batch.site?.name ? ` · ${batch.site.name}` : ''}
                </p>
                <p className="mt-1 text-xs text-ink-700">
                  Issued {batch.issued_count ?? '—'} · unused {batch.unused_count ?? '—'} · printed {batch.print_count}×
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={batch.status === 'ready' ? 'green' : batch.status === 'disabled' ? 'red' : 'amber'}>
                  {batch.status_label}
                </Badge>
                {batch.is_printable && (
                  <button type="button" className={primaryBtn} onClick={() => void print(batch)}>
                    Print
                  </button>
                )}
                {batch.status !== 'disabled' && (
                  <button type="button" className={secondaryBtn} onClick={() => disable.mutate(batch.id)}>
                    Withdraw
                  </button>
                )}
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!batches.data?.data.length && <Empty>{batches.isLoading ? 'Loading batches…' : 'No batches yet.'}</Empty>}
    </div>
  );
}
