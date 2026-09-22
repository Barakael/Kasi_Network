import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import { Badge, Card, Empty, PageHeader } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

export function ReportsPage() {
  const { user } = useAuth();
  const revenue = useQuery({ queryKey: ['revenue'], queryFn: api.revenue });
  const orders = useQuery({ queryKey: ['orders'], queryFn: api.orders });
  const currency = user?.tenant?.currency ?? 'TZS';

  return (
    <div>
      <PageHeader title="Reports" subtitle="Paid mobile-money orders and daily totals." />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Revenue by day</h2>
          {(revenue.data?.data.length ?? 0) === 0 ? (
            <Empty>No settled orders in the last 30 days.</Empty>
          ) : (
            <ul className="space-y-2 text-sm">
              {revenue.data?.data.map((row) => (
                <li key={row.day} className="flex justify-between">
                  <span>{row.day}</span>
                  <span>
                    {row.orders} orders · {money(Number(row.total), currency)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Recent orders</h2>
          {(orders.data?.data.length ?? 0) === 0 ? (
            <Empty>No orders yet.</Empty>
          ) : (
            <ul className="space-y-2 text-sm">
              {orders.data?.data.map((order) => (
                <li key={order.uuid} className="flex items-center justify-between gap-2">
                  <span>
                    {order.plan?.name ?? 'Bundle'} · {order.phone}
                  </span>
                  <Badge tone={order.status === 'fulfilled' ? 'green' : order.status === 'failed' ? 'red' : 'amber'}>
                    {order.status_label}
                  </Badge>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </div>
  );
}
