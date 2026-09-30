import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../api';
import { Card, PageHeader } from '../components/ui';
import { usePrefs } from '../preferences';

export function PlatformOverviewPage() {
  const { sw } = usePrefs();
  const overview = useQuery({ queryKey: ['platform-overview'], queryFn: api.platformOverview, refetchInterval: 30_000 });
  const tenants = useQuery({ queryKey: ['platform-tenants'], queryFn: api.platformTenants });
  const invoices = useQuery({ queryKey: ['platform-invoices'], queryFn: api.platformInvoices });
  const data = overview.data;
  const operators = tenants.data?.data ?? [];
  const openInvoices = (invoices.data?.data ?? []).filter((row) => row.status !== 'paid');

  return (
    <div>
      <PageHeader title={sw('Muhtasari', 'Overview')} subtitle={sw('Waendeshaji, Lipia, na ankara za mfumo — hazichanganyiki na makusanyo ya eneo.', 'Operators, Lipia, and platform invoices — never mixed into a hotspot till.')} />
      {overview.isError && <p className="mb-4 text-red-700">{(overview.error as Error).message}</p>}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Link to="/platform/operators">
          <Card className="transition hover:border-brand-400">
            <p className="text-sm text-ink-700">{sw('Waendeshaji hai', 'Active operators')}</p>
            <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{data?.operators_active ?? '—'}</p>
            <p className="mt-1 text-sm text-ink-700">{data?.operators_suspended ?? 0} {sw('wamesimamishwa', 'suspended')}</p>
          </Card>
        </Link>
        <Link to="/platform/operators">
          <Card className="transition hover:border-brand-400">
            <p className="text-sm text-ink-700">{sw('Lipia imewekwa', 'Lipia is set')}</p>
            <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{data?.lipia_on ?? '—'}</p>
            <p className="mt-1 text-sm text-ink-700">{sw('PalmPesa kwenye mwendeshaji', 'PalmPesa on the operator')}</p>
          </Card>
        </Link>
        <Link to="/platform/invoices">
          <Card className="transition hover:border-brand-400">
            <p className="text-sm text-ink-700">{sw('Ankara wazi', 'Open invoices')}</p>
            <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{data?.invoices_open ?? '—'}</p>
            <p className="mt-1 text-sm text-ink-700">{data?.invoices_paid ?? 0} {sw('zilizolipwa', 'paid')}</p>
          </Card>
        </Link>
        <Card>
          <p className="text-sm text-ink-700">{sw('Jumla ya waendeshaji', 'All operators')}</p>
          <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{operators.length || '—'}</p>
          <p className="mt-1 text-sm text-ink-700">{sw('Wasimamizi wa mfumo', 'System administrators')}</p>
        </Card>
      </div>
      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <Card>
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Waendeshaji', 'Operators')}</h2>
            <Link to="/platform/operators" className="text-sm font-semibold text-brand-700 hover:underline">
              {sw('Alika', 'Invite')}
            </Link>
          </div>
          <ul className="space-y-2">
            {operators.slice(0, 8).map((tenant) => (
              <li key={tenant.uuid} className="flex justify-between gap-2 text-sm">
                <span className="font-medium text-ink-900">{tenant.portal_name || tenant.name}</span>
                <span className="text-ink-700">{tenant.status ?? 'active'}</span>
              </li>
            ))}
          </ul>
          {operators.length === 0 && <p className="text-sm text-ink-700">{sw('Bado hakuna mwendeshaji.', 'No operator yet.')}</p>}
        </Card>
        <Card>
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Ankara wazi', 'Open invoices')}</h2>
            <Link to="/platform/invoices" className="text-sm font-semibold text-brand-700 hover:underline">
              {sw('Ankara', 'Invoices')}
            </Link>
          </div>
          <ul className="space-y-2">
            {openInvoices.slice(0, 8).map((row) => (
              <li key={row.id} className="flex justify-between gap-2 text-sm">
                <span className="font-medium text-ink-900">{row.tenant?.portal_name || row.tenant?.name || 'Operator'}</span>
                <span className="text-ink-700">{row.period_label || row.status}</span>
              </li>
            ))}
          </ul>
          {openInvoices.length === 0 && <p className="text-sm text-ink-700">{sw('Hakuna ankara wazi.', 'No open invoice.')}</p>}
        </Card>
      </div>
    </div>
  );
}
