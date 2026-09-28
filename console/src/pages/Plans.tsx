import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api, type Plan } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { bytes, money } from '../format';
import { useAuth } from '../auth';

const emptyForm = {
  name: '',
  billing_period: 'daily',
  price_minor: 1500,
  device_limit: 1,
  on_quota_exhausted: 'disconnect',
  data_cap_bytes: '',
  duration_seconds: '',
  rate_limit_down_kbps: 5120,
  rate_limit_up_kbps: 2048,
  is_sold_online: true,
};

export function PlansPage() {
  const { user } = useAuth();
  const client = useQueryClient();
  const plans = useQuery({ queryKey: ['plans'], queryFn: api.plans });
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      api.createPlan({
        ...form,
        price_minor: Number(form.price_minor),
        device_limit: Number(form.device_limit),
        rate_limit_down_kbps: Number(form.rate_limit_down_kbps) || null,
        rate_limit_up_kbps: Number(form.rate_limit_up_kbps) || null,
        data_cap_bytes: form.data_cap_bytes === '' ? null : Number(form.data_cap_bytes),
        duration_seconds: form.duration_seconds === '' ? null : Number(form.duration_seconds),
      }),
    onSuccess: () => {
      setOpen(false);
      setForm(emptyForm);
      void client.invalidateQueries({ queryKey: ['plans'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const retire = useMutation({
    mutationFn: api.retirePlan,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['plans'] }),
    onError: (err: Error) => setError(err.message),
  });

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    create.mutate();
  }

  return (
    <div>
      <PageHeader title="Packages" subtitle="Prices sold at the portal and on printed cards. Customers never see kbps.">
        <button type="button" className={primaryBtn} onClick={() => setOpen((v) => !v)}>
          {open ? 'Close' : 'New bundle'}
        </button>
      </PageHeader>
      <ErrorBanner message={error} />
      {open && (
        <Card className="mb-4">
          <form onSubmit={onSubmit} className="grid gap-3 sm:grid-cols-2">
            <Field label="Name">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </Field>
            <Field label="Period">
              <select className={inputClass} value={form.billing_period} onChange={(e) => setForm({ ...form, billing_period: e.target.value })}>
                <option value="hourly">Hourly</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="custom">Custom</option>
              </select>
            </Field>
            <Field label="Price (minor units)">
              <input className={inputClass} type="number" min={0} value={form.price_minor} onChange={(e) => setForm({ ...form, price_minor: Number(e.target.value) })} />
            </Field>
            <Field label="Devices">
              <input className={inputClass} type="number" min={1} max={20} value={form.device_limit} onChange={(e) => setForm({ ...form, device_limit: Number(e.target.value) })} />
            </Field>
            <Field label="Download kbps">
              <input className={inputClass} type="number" value={form.rate_limit_down_kbps} onChange={(e) => setForm({ ...form, rate_limit_down_kbps: Number(e.target.value) })} />
            </Field>
            <Field label="Upload kbps">
              <input className={inputClass} type="number" value={form.rate_limit_up_kbps} onChange={(e) => setForm({ ...form, rate_limit_up_kbps: Number(e.target.value) })} />
            </Field>
            <Field label="Data cap bytes (empty = none)">
              <input className={inputClass} value={form.data_cap_bytes} onChange={(e) => setForm({ ...form, data_cap_bytes: e.target.value })} />
            </Field>
            <label className="flex items-center gap-2 text-sm text-ink-800">
              <input type="checkbox" checked={form.is_sold_online} onChange={(e) => setForm({ ...form, is_sold_online: e.target.checked })} />
              Sell online
            </label>
            <div className="sm:col-span-2">
              <button type="submit" className={primaryBtn} disabled={create.isPending}>
                Save bundle
              </button>
            </div>
          </form>
        </Card>
      )}
      <div className="grid gap-3 sm:grid-cols-2">
        {(plans.data?.data ?? []).map((plan) => (
          <PlanCard
            key={plan.id}
            plan={plan}
            currency={user?.tenant?.currency ?? 'TZS'}
            onRetire={() => retire.mutate(plan.id)}
          />
        ))}
      </div>
      {!plans.data?.data.length && <Empty>{plans.isLoading ? 'Loading bundles…' : 'No bundles yet.'}</Empty>}
    </div>
  );
}

function PlanCard({ plan, currency, onRetire }: { plan: Plan; currency: string; onRetire: () => void }) {
  const client = useQueryClient();
  const [offer, setOffer] = useState({ price_minor: plan.price_minor, offer_label: '', offer_ends_at: '' });

  return (
    <Card>
      <div className="flex items-start justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold text-ink-900">{plan.name}</h2>
          <p className="text-sm text-ink-700">{plan.billing_period_label}</p>
        </div>
        <Badge tone={plan.has_active_offer ? 'amber' : plan.is_active === false ? 'slate' : 'green'}>
          {money(plan.price_minor, currency)}
        </Badge>
      </div>
      {plan.has_active_offer && <p className="mt-2 text-sm text-amber-800">{plan.offer_label}</p>}
      <p className="mt-2 text-sm text-ink-800">
        {bytes(plan.data_cap_bytes)} · {plan.device_limit} device{plan.device_limit === 1 ? '' : 's'}
      </p>
      {plan.has_active_offer ? (
        <button
          type="button"
          className={`${secondaryBtn} mt-3`}
          onClick={() => void api.restorePlanPrice(plan.id).then(() => client.invalidateQueries({ queryKey: ['plans'] }))}
        >
          Rudi bei
        </button>
      ) : (
        <form
          className="mt-3 grid gap-2"
          onSubmit={(event) => {
            event.preventDefault();
            void api.offerPlan(plan.id, {
              ...offer,
              offer_ends_at: offer.offer_ends_at ? new Date(offer.offer_ends_at).toISOString() : '',
            }).then(() => client.invalidateQueries({ queryKey: ['plans'] }));
          }}
        >
          <input className={inputClass} placeholder="Wiki Moja 4000" value={offer.offer_label} onChange={(e) => setOffer({ ...offer, offer_label: e.target.value })} />
          <input className={inputClass} type="number" value={offer.price_minor} onChange={(e) => setOffer({ ...offer, price_minor: Number(e.target.value) })} />
          <input className={inputClass} type="datetime-local" value={offer.offer_ends_at} onChange={(e) => setOffer({ ...offer, offer_ends_at: e.target.value })} />
          <button type="submit" className={secondaryBtn}>
            Set offer
          </button>
        </form>
      )}
      <button type="button" className={`${secondaryBtn} mt-3`} onClick={onRetire}>
        Retire
      </button>
    </Card>
  );
}
