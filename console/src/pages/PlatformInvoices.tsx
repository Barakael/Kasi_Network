import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn } from '../components/ui';
import { money } from '../format';
import { usePrefs } from '../preferences';

export function PlatformInvoicesPage() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const invoices = useQuery({ queryKey: ['platform-invoices'], queryFn: api.platformInvoices });
  const tenants = useQuery({ queryKey: ['platform-tenants'], queryFn: api.platformTenants });
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ tenant_uuid: '', amount_minor: 50000, period_label: '' });

  const create = useMutation({
    mutationFn: () => api.createPlatformInvoice(form),
    onSuccess: () => {
      setForm({ ...form, period_label: '' });
      void client.invalidateQueries({ queryKey: ['platform-invoices'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title={sw('Ankara', 'Invoices')} subtitle={sw('Wasimamizi wanadaiwa na Kasi. Hazichanganyiki na makusanyo ya eneo.', 'What administrators owe Kasi. Never mixed into hotspot collections.')} />
      <ErrorBanner message={error} />
      <Card className="mb-4">
        <form
          className="grid gap-3 sm:grid-cols-3"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          <Field label={sw('Mwendeshaji', 'Operator')}>
            <select className={inputClass} value={form.tenant_uuid} onChange={(e) => setForm({ ...form, tenant_uuid: e.target.value })} required>
              <option value="">{sw('Chagua…', 'Select…')}</option>
              {(tenants.data?.data ?? []).map((tenant) => (
                <option key={tenant.uuid} value={tenant.uuid}>
                  {tenant.portal_name || tenant.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label={sw('Kiasi (TZS)', 'Amount (TZS)')}>
            <input className={inputClass} type="number" value={form.amount_minor} onChange={(e) => setForm({ ...form, amount_minor: Number(e.target.value) })} />
          </Field>
          <Field label={sw('Kipindi', 'Period')}>
            <input className={inputClass} value={form.period_label} onChange={(e) => setForm({ ...form, period_label: e.target.value })} />
          </Field>
          <button type="submit" className={primaryBtn} disabled={create.isPending}>
            {sw('Toa ankara', 'Issue')}
          </button>
        </form>
      </Card>
      <div className="space-y-3">
        {(invoices.data?.data ?? []).map((row) => (
          <Card key={row.id}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{row.tenant?.portal_name || row.tenant?.name || 'Operator'}</p>
                <p className="text-sm text-ink-700">
                  {money(row.amount_minor, row.currency)} · {row.period_label}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={row.status === 'paid' ? 'green' : 'amber'}>{row.status}</Badge>
                {row.status !== 'paid' && (
                  <button
                    type="button"
                    className={primaryBtn}
                    onClick={() => {
                      void api.markInvoicePaid(row.id).then(() => client.invalidateQueries({ queryKey: ['platform-invoices'] }));
                    }}
                  >
                    {sw('Weka imelipwa', 'Mark paid')}
                  </button>
                )}
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!invoices.data?.data.length && <Empty>{invoices.isLoading ? sw('Inapakia…', 'Loading…') : sw('Bado hakuna ankara.', 'No invoices yet.')}</Empty>}
    </div>
  );
}
