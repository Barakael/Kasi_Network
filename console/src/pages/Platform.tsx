import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';

export function PlatformPage() {
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
      <PageHeader title="Operators" subtitle="Invite System Administrators. Their till never mixes with yours." />
      <ErrorBanner message={error} />
      {password && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          One-time password: <strong>{password}</strong>
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
          <Field label="Business name">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label="Portal name">
            <input className={inputClass} value={form.portal_name} onChange={(e) => setForm({ ...form, portal_name: e.target.value })} />
          </Field>
          <Field label="Admin name">
            <input className={inputClass} value={form.admin_name} onChange={(e) => setForm({ ...form, admin_name: e.target.value })} required />
          </Field>
          <Field label="Admin email">
            <input className={inputClass} type="email" value={form.admin_email} onChange={(e) => setForm({ ...form, admin_email: e.target.value })} required />
          </Field>
          <button type="submit" className={primaryBtn} disabled={create.isPending}>
            Invite System Administrator
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
                  Lipia: {tenant.accepts_online_payments ? 'Imewekwa' : 'Haijawekwa'}
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
                  {tenant.status === 'suspended' ? 'Activate' : 'Suspend'}
                </button>
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!tenants.data?.data.length && <Empty>{tenants.isLoading ? 'Loading…' : 'No operators yet.'}</Empty>}
    </div>
  );
}
