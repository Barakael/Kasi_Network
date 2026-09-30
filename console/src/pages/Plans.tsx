import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api, type Plan } from '../api';
import { ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

function hoursOf(plan: Plan): number {
  const seconds = plan.duration_seconds || plan.validity_seconds || 3600;
  return Math.max(1, Math.round(seconds / 3600));
}

function periodLabel(plan: Plan, sw: (kiswahili: string, english: string) => string): string {
  if (plan.billing_period === 'hourly') {
    const hours = hoursOf(plan);
    return sw(`Saa ${hours}`, `${hours} h`);
  }
  if (plan.billing_period === 'daily') return sw('Siku', 'Day');
  if (plan.billing_period === 'weekly') return sw('Wiki', 'Week');
  if (plan.billing_period === 'monthly') return sw('Mwezi', 'Month');
  if (plan.billing_period === 'custom') return sw('Maalum', 'Custom');
  return plan.billing_period_label;
}

export function PlansPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const client = useQueryClient();
  const plans = useQuery({ queryKey: ['plans'], queryFn: api.plans });
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<number | null>(null);
  const currency = user?.tenant?.currency ?? 'TZS';

  const retire = useMutation({
    mutationFn: api.retirePlan,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['plans'] }),
    onError: (err: Error) => setError(err.message),
  });

  const save = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) => api.updatePlan(id, payload),
    onSuccess: () => {
      setEditing(null);
      void client.invalidateQueries({ queryKey: ['plans'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title={sw('Vifurushi', 'Packages')} />
      <ErrorBanner message={error} />
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
        {(plans.data?.data ?? []).map((plan) => (
          <article key={plan.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="truncate text-sm font-semibold text-ink-900">{plan.name}</p>
            <p className="mt-1 text-xs text-ink-700">{periodLabel(plan, sw)}</p>
            <p className="mt-2 text-lg font-bold text-ink-900">{money(plan.price_minor, currency)}</p>
            {editing === plan.id ? (
              <PlanEditor
                plan={plan}
                onCancel={() => setEditing(null)}
                onSave={(payload) => save.mutate({ id: plan.id, payload })}
              />
            ) : (
              <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" className={secondaryBtn} onClick={() => setEditing(plan.id)}>
                  {sw('Hariri', 'Edit')}
                </button>
                <button type="button" className={secondaryBtn} onClick={() => retire.mutate(plan.id)}>
                  {sw('Futa', 'Delete')}
                </button>
              </div>
            )}
          </article>
        ))}
      </div>
    </div>
  );
}

function PlanEditor({
  plan,
  onCancel,
  onSave,
}: {
  plan: Plan;
  onCancel: () => void;
  onSave: (payload: Record<string, unknown>) => void;
}) {
  const { sw } = usePrefs();
  const hourly = plan.billing_period === 'hourly';
  const [hours, setHours] = useState(hoursOf(plan));
  const [price, setPrice] = useState(plan.price_minor);

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (hourly) {
      const seconds = Math.max(1, hours) * 3600;
      onSave({ price_minor: price, validity_seconds: seconds, duration_seconds: seconds });
      return;
    }
    onSave({ price_minor: price });
  }

  return (
    <form className="mt-3 space-y-2" onSubmit={onSubmit}>
      {hourly && (
        <Field label={sw('Masaa', 'Hours')}>
          <input className={inputClass} type="number" min={1} value={hours} onChange={(e) => setHours(Number(e.target.value))} />
        </Field>
      )}
      <Field label={sw('Bei', 'Price')}>
        <input className={inputClass} type="number" min={0} value={price} onChange={(e) => setPrice(Number(e.target.value))} />
      </Field>
      <div className="flex gap-2">
        <button type="submit" className={primaryBtn}>
          {sw('Hifadhi', 'Save')}
        </button>
        <button type="button" className={secondaryBtn} onClick={onCancel}>
          ×
        </button>
      </div>
    </form>
  );
}
