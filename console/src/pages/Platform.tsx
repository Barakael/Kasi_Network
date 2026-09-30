import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState, type FormEvent } from 'react';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { usePrefs } from '../preferences';

export function PlatformPage() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const tenants = useQuery({ queryKey: ['platform-tenants'], queryFn: api.platformTenants });
  const [error, setError] = useState<string | null>(null);
  const [password, setPassword] = useState<string | null>(null);
  const [form, setForm] = useState({ name: '', portal_name: '', admin_name: '', admin_email: '' });

  const create = useMutation({
    mutationFn: () => api.createPlatformTenant(form),
    onSuccess: (body) => {
      setPassword(body.password);
      setForm({ name: '', portal_name: '', admin_name: '', admin_email: '' });
      void client.invalidateQueries({ queryKey: ['platform-tenants'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title={sw('Waendeshaji', 'Operators')} subtitle={sw('Alika wasimamizi. Makusanyo yao hayachanganyiki na yako.', 'Invite administrators. Their till never mixes with yours.')} />
      <ErrorBanner message={error} />
      <PaymentDetails />
      {password && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          {sw('Nenosiri la mara moja', 'One-time password')}: <strong>{password}</strong>
        </p>
      )}
      <Card className="mb-4">
        <form
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          <Field label={sw('Jina la biashara', 'Business name')}>
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label={sw('Jina la portal', 'Portal name')}>
            <input className={inputClass} value={form.portal_name} onChange={(e) => setForm({ ...form, portal_name: e.target.value })} />
          </Field>
          <Field label={sw('Jina la msimamizi', 'Admin name')}>
            <input className={inputClass} value={form.admin_name} onChange={(e) => setForm({ ...form, admin_name: e.target.value })} required />
          </Field>
          <Field label={sw('Barua pepe ya msimamizi', 'Admin email')}>
            <input className={inputClass} type="email" value={form.admin_email} onChange={(e) => setForm({ ...form, admin_email: e.target.value })} required />
          </Field>
          <button type="submit" className={primaryBtn} disabled={create.isPending}>
            {sw('Alika msimamizi', 'Invite administrator')}
          </button>
        </form>
      </Card>
      <div className="space-y-3">
        {(tenants.data?.data ?? []).map((tenant) => (
          <Card key={tenant.uuid}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{tenant.portal_name || tenant.name}</p>
                <p className="text-sm text-ink-700">
                  Lipia: {tenant.accepts_online_payments ? sw('Imewekwa', 'Set up') : sw('Haijawekwa', 'Not set')}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={tenant.status === 'active' ? 'green' : 'slate'}>{tenant.status ?? 'active'}</Badge>
                <button
                  type="button"
                  className={secondaryBtn}
                  onClick={() => {
                    const next = tenant.status === 'suspended' ? 'active' : 'suspended';
                    void api
                      .updatePlatformTenant(tenant.uuid, { status: next })
                      .then(() => client.invalidateQueries({ queryKey: ['platform-tenants'] }))
                      .catch((err: Error) => setError(err.message));
                  }}
                >
                  {tenant.status === 'suspended' ? sw('Washa', 'Activate') : sw('Simamisha', 'Suspend')}
                </button>
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!tenants.data?.data.length && <Empty>{tenants.isLoading ? sw('Inapakia…', 'Loading…') : sw('Bado hakuna waendeshaji.', 'No operators yet.')}</Empty>}
    </div>
  );
}

function PaymentDetails() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const details = useQuery({ queryKey: ['platform-payment-details'], queryFn: api.platformPaymentDetails });
  const [form, setForm] = useState({ payee_name: '', account_number: '', instructions: '' });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const row = details.data?.data;
    if (!row) {
      return;
    }
    setForm({
      payee_name: row.payee_name ?? '',
      account_number: row.account_number ?? '',
      instructions: row.instructions ?? '',
    });
  }, [details.data]);

  const save = useMutation({
    mutationFn: () => api.updatePlatformPaymentDetails(form),
    onSuccess: () => void client.invalidateQueries({ queryKey: ['platform-payment-details'] }),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <Card className="mb-4">
      <h2 className="mb-3 text-sm font-semibold text-ink-700">{sw('Malipo ya mfumo', 'Platform billing')}</h2>
      <ErrorBanner message={error} />
      <form
        className="grid gap-3 sm:grid-cols-2"
        onSubmit={(event: FormEvent) => {
          event.preventDefault();
          save.mutate();
        }}
      >
        <Field label={sw('Jina la akaunti', 'Account name')}>
          <input className={inputClass} value={form.payee_name} onChange={(e) => setForm({ ...form, payee_name: e.target.value })} />
        </Field>
        <Field label={sw('Namba', 'Number')}>
          <input className={inputClass} value={form.account_number} onChange={(e) => setForm({ ...form, account_number: e.target.value })} />
        </Field>
        <Field label={sw('Maelezo', 'Details')}>
          <textarea className={inputClass} value={form.instructions} onChange={(e) => setForm({ ...form, instructions: e.target.value })} />
        </Field>
        <div className="flex items-end">
          <button type="submit" className={primaryBtn} disabled={save.isPending}>
            {sw('Hifadhi', 'Save')}
          </button>
        </div>
      </form>
    </Card>
  );
}
