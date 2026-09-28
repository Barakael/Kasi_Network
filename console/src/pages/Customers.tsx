import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, type Customer } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, primaryBtn, secondaryBtn } from '../components/ui';

export function CustomersPage() {
  const client = useQueryClient();
  const [tab, setTab] = useState<'hai' | 'kimya'>('hai');
  const [error, setError] = useState<string | null>(null);
  const [code, setCode] = useState<string | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const customers = useQuery({ queryKey: ['customers', tab], queryFn: () => api.customers(tab) });

  const reveal = useMutation({
    mutationFn: api.revealCustomerVoucher,
    onSuccess: (body) => setCode(body.code),
    onError: (err: Error) => setError(err.message),
  });

  const disconnect = useMutation({
    mutationFn: api.disconnect,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['customers'] }),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title="Wateja" subtitle="Hai have access now. Kimya have a phone and no live bundle — campaign list." />
      <ErrorBanner message={error} />
      {code && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          Tuma tena: <strong>{code}</strong>
        </p>
      )}
      <div className="mb-4 flex gap-2">
        <button type="button" className={tab === 'hai' ? primaryBtn : secondaryBtn} onClick={() => setTab('hai')}>
          Hai
        </button>
        <button type="button" className={tab === 'kimya' ? primaryBtn : secondaryBtn} onClick={() => setTab('kimya')}>
          Kimya
        </button>
      </div>
      <div className="space-y-3">
        {(customers.data?.data ?? []).map((row: Customer) => {
          const open = openId === row.id;
          return (
            <Card key={row.id}>
              <button
                type="button"
                className="flex w-full flex-wrap items-center justify-between gap-2 text-left"
                onClick={() => setOpenId(open ? null : row.id)}
              >
                <div>
                  <p className="font-semibold">{row.phone}</p>
                  <p className="text-sm text-ink-700">{row.site?.name ?? '—'}</p>
                </div>
                <Badge tone={row.status === 'hai' ? 'green' : 'slate'}>{row.status === 'hai' ? 'Hai' : 'Kimya'}</Badge>
              </button>
              {open && (
                <div className="mt-3 space-y-2 border-t border-slate-200 pt-3 text-sm text-ink-800">
                  <p>Last package: {row.last_package ?? '—'}</p>
                  <p>Paid via: {row.paid_via === 'kadi' ? 'kadi' : row.paid_via === 'simu' ? 'simu' : '—'}</p>
                  {row.has_unused_voucher && (
                    <button
                      type="button"
                      className={secondaryBtn}
                      onClick={() => {
                        setError(null);
                        reveal.mutate(row.id);
                      }}
                    >
                      Tuma tena
                    </button>
                  )}
                  {row.session_id && (
                    <button
                      type="button"
                      className={secondaryBtn}
                      onClick={() => {
                        setError(null);
                        disconnect.mutate(row.session_id as string);
                      }}
                    >
                      Kata
                    </button>
                  )}
                </div>
              )}
            </Card>
          );
        })}
      </div>
      {!customers.data?.data.length && <Empty>{customers.isLoading ? 'Loading…' : 'No customers in this tab.'}</Empty>}
    </div>
  );
}
