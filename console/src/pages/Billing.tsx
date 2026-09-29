import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../api';
import { Badge, Callout, Card, Empty, PageHeader } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

export function BillingPage() {
  const { user } = useAuth();
  const invoices = useQuery({ queryKey: ['billing-invoices'], queryFn: api.invoices });
  const currency = user?.tenant?.currency ?? 'TZS';
  const rows = invoices.data?.data ?? [];
  const open = rows.filter((row) => row.status !== 'paid');

  return (
    <div>
      <PageHeader
        title="Billing"
        subtitle="Unachodaiwa Kasi Network. Hii si Lipia wala kadi — Collections ni pesa ya wateja wako."
      />
      <Callout>
        Lipia na kadi ziko kwenye{' '}
        <Link to="/collections" className="font-semibold underline">
          Collections
        </Link>
        . Ukurasa huu ni bili ya platform pekee.
      </Callout>
      {open.length > 0 && (
        <Callout tone="amber">
          {open.length} bili {open.length === 1 ? 'haijalipwa' : 'hazijalipwa'}. Lipia kwa njia uliyopewa na Kasi — usichanganye na PalmPesa ya wateja.
        </Callout>
      )}
      <div className="space-y-3">
        {rows.map((row) => (
          <Card key={row.id}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold text-ink-900">{row.period_label || 'Invoice'}</p>
                <p className="text-sm text-ink-700">{money(row.amount_minor, row.currency || currency)}</p>
                {row.due_at && <p className="text-xs text-ink-700">Due {row.due_at.slice(0, 10)}</p>}
                {row.paid_at && <p className="text-xs text-ink-700">Paid {row.paid_at.slice(0, 10)}</p>}
              </div>
              <Badge tone={row.status === 'paid' ? 'green' : 'amber'}>{row.status}</Badge>
            </div>
          </Card>
        ))}
      </div>
      {!rows.length && <Empty>{invoices.isLoading ? 'Inapakia…' : 'Hakuna bili ya platform bado.'}</Empty>}
    </div>
  );
}
