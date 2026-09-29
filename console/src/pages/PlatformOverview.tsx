import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../api';
import { Card, PageHeader } from '../components/ui';

export function PlatformOverviewPage() {
  const overview = useQuery({ queryKey: ['platform-overview'], queryFn: api.platformOverview, refetchInterval: 30_000 });
  const tenants = useQuery({ queryKey: ['platform-tenants'], queryFn: api.platformTenants });
  const invoices = useQuery({ queryKey: ['platform-invoices'], queryFn: api.platformInvoices });
  const data = overview.data;
  const operators = tenants.data?.data ?? [];
  const openInvoices = (invoices.data?.data ?? []).filter((row) => row.status !== 'paid');

  return (
    <div>
      <PageHeader title="Overview" subtitle="Operators, Lipia, and platform invoices — never mixed into a hotspot till." />
      {overview.isError && <p className="mb-4 text-red-700">{(overview.error as Error).message}</p>}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Link to="/platform/operators">
          <Card className="transition hover:border-brand-400">
            <p className="text-sm text-ink-700">Operators hai</p>
            <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{data?.operators_active ?? '—'}</p>
            <p className="mt-1 text-sm text-ink-700">{data?.operators_suspended ?? 0} suspended</p>
          </Card>
        </Link>
        <Link to="/platform/operators">
          <Card className="transition hover:border-brand-400">
            <p className="text-sm text-ink-700">Lipia imewekwa</p>
            <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{data?.lipia_on ?? '—'}</p>
            <p className="mt-1 text-sm text-ink-700">PalmPesa kwenye operator</p>
          </Card>
        </Link>
        <Link to="/platform/invoices">
          <Card className="transition hover:border-brand-400">
            <p className="text-sm text-ink-700">Invoices wazi</p>
            <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{data?.invoices_open ?? '—'}</p>
            <p className="mt-1 text-sm text-ink-700">{data?.invoices_paid ?? 0} zilizolipwa</p>
          </Card>
        </Link>
        <Card>
          <p className="text-sm text-ink-700">Jumla ya operators</p>
          <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{operators.length || '—'}</p>
          <p className="mt-1 text-sm text-ink-700">System Administrators</p>
        </Card>
      </div>
      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <Card>
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Operators</h2>
            <Link to="/platform/operators" className="text-sm font-semibold text-brand-700 hover:underline">
              Invite
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
          {operators.length === 0 && <p className="text-sm text-ink-700">Bado hakuna operator.</p>}
        </Card>
        <Card>
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Invoices wazi</h2>
            <Link to="/platform/invoices" className="text-sm font-semibold text-brand-700 hover:underline">
              Bili
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
          {openInvoices.length === 0 && <p className="text-sm text-ink-700">Hakuna invoice wazi.</p>}
        </Card>
      </div>
    </div>
  );
}
