import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api, openPrintSheet, type Batch } from '../api';
import { Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { usePrefs } from '../preferences';

export function BatchesPage() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const batches = useQuery({ queryKey: ['batches'], queryFn: api.batches, refetchInterval: 8_000 });
  const plans = useQuery({ queryKey: ['plans'], queryFn: api.plans });
  const [error, setError] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ plan_id: '', quantity: 10, reference: '', expires_on: '' });

  const create = useMutation({
    mutationFn: () =>
      api.createBatch({
        plan_id: Number(form.plan_id),
        quantity: Number(form.quantity),
        reference: form.reference || null,
        expires_on: form.expires_on || null,
      }),
    onSuccess: () => {
      setOpen(false);
      setForm({ ...form, reference: '', expires_on: '' });
      void client.invalidateQueries({ queryKey: ['batches'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  async function download(batch: Batch) {
    setError(null);
    try {
      await openPrintSheet(batch.id, batch.printable_count || batch.quantity);
    } catch (err) {
      setError((err as Error).message);
    }
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    create.mutate();
  }

  const rows = batches.data?.data ?? [];

  return (
    <div>
      <PageHeader title={sw('Vocha', 'Vouchers')}>
        <button type="button" className={primaryBtn} onClick={() => setOpen((value) => !value)}>
          {sw('Tengeneza vocha', 'New voucher pack')}
        </button>
      </PageHeader>
      <ErrorBanner message={error} />
      {open && (
        <form onSubmit={onSubmit} className="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
          <Field label={sw('Kifurushi', 'Package')}>
            <select className={inputClass} value={form.plan_id} onChange={(e) => setForm({ ...form, plan_id: e.target.value })} required>
              <option value="">{sw('Kifurushi', 'Package')}</option>
              {(plans.data?.data ?? []).map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label={sw('Idadi', 'Quantity')}>
            <input className={inputClass} type="number" min={1} value={form.quantity} onChange={(e) => setForm({ ...form, quantity: Number(e.target.value) })} />
          </Field>
          <Field label={sw('Kumbukumbu', 'Reference')}>
            <input className={inputClass} value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} />
          </Field>
          <Field label={sw('Tumia ifikapo (si lazima)', 'Use by (optional)')}>
            <input className={inputClass} type="date" value={form.expires_on} onChange={(e) => setForm({ ...form, expires_on: e.target.value })} />
          </Field>
          <div className="sm:col-span-2">
            <button type="submit" className={primaryBtn} disabled={create.isPending}>
              {sw('Hifadhi', 'Save')}
            </button>
          </div>
        </form>
      )}
      <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        <table className="console-table table-fixed">
          <thead>
            <tr>
              <th>{sw('Kumbukumbu', 'Reference')}</th>
              <th className="hidden sm:table-cell">{sw('Kifurushi', 'Package')}</th>
              <th className="w-12">{sw('Idadi', 'Quantity')}</th>
              <th className="hidden w-28 md:table-cell">{sw('Tarehe', 'Date')}</th>
              <th className="w-[6.5rem]" />
            </tr>
          </thead>
          <tbody>
            {rows.map((batch) => (
              <tr key={batch.id}>
                <td className="font-semibold">{batch.reference || '—'}</td>
                <td className="hidden sm:table-cell">{batch.plan?.name ?? '—'}</td>
                <td>{batch.quantity}</td>
                <td className="hidden md:table-cell">{batch.created_at?.slice(0, 10) ?? '—'}</td>
                <td>
                  <button type="button" className={`${secondaryBtn} w-full px-2 text-sm`} onClick={() => void download(batch)}>
                    {sw('Pakua PDF', 'Download PDF')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {!rows.length && <Empty>{batches.isLoading ? '…' : sw('Vocha', 'Vouchers')}</Empty>}
      </div>
    </div>
  );
}
