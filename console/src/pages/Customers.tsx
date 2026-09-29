import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router';
import { api, type Customer } from '../api';
import { Badge, Callout, Card, Empty, ErrorBanner, PageHeader, Segmented, inputClass, secondaryBtn } from '../components/ui';
import { ago } from '../format';

type Tab = 'online' | 'hai' | 'kimya';

export function CustomersPage() {
  const client = useQueryClient();
  const [tab, setTab] = useState<Tab>('online');
  const [search, setSearch] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [code, setCode] = useState<string | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const insights = useQuery({ queryKey: ['insights', 'day'], queryFn: () => api.insights('day'), refetchInterval: 30_000 });
  const customers = useQuery({
    queryKey: ['customers', tab, search],
    queryFn: () => api.customers(tab, undefined, search || undefined),
    refetchInterval: 20_000,
  });

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

  const counts = insights.data?.customers;
  const rows = customers.data?.data ?? [];

  return (
    <div>
      <PageHeader
        title="Wateja"
        subtitle="Mtandaoni ni wenye session sasa hivi. Hai wana bundle. Kimya wana namba tu — hao ndio orodha ya notices."
      />
      <ErrorBanner message={error} />
      {code && (
        <Callout tone="amber">
          Tuma tena: <strong>{code}</strong>
        </Callout>
      )}
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <Segmented
          value={tab}
          onChange={setTab}
          label="Hali ya mteja"
          options={[
            { value: 'online', label: `Mtandaoni ${counts ? counts.online_now : ''}`.trim() },
            { value: 'hai', label: `Hai ${counts ? counts.hai : ''}`.trim() },
            { value: 'kimya', label: `Kimya ${counts ? counts.kimya : ''}`.trim() },
          ]}
        />
        <input
          className={`${inputClass} max-w-xs`}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Tafuta 07… au MAC"
          aria-label="Tafuta mteja"
        />
        <Link to="/devices" className={`${secondaryBtn} no-underline`}>
          Vifaa
        </Link>
      </div>
      <div className="space-y-3">
        {rows.map((row: Customer) => {
          const open = openId === row.id;
          const online = Boolean(row.session_id);
          return (
            <Card key={row.id}>
              <button
                type="button"
                className="flex w-full flex-wrap items-center justify-between gap-2 text-left"
                onClick={() => setOpenId(open ? null : row.id)}
              >
                <div className="min-w-0">
                  <p className="font-semibold text-ink-900">{row.phone}</p>
                  <p className="text-sm text-ink-700">
                    {row.site?.name ?? '—'} · {row.last_package ?? 'Bado hajanunua'}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-ink-700">{ago(row.last_seen_at)}</span>
                  {online ? (
                    <Badge tone="green">Mtandaoni</Badge>
                  ) : (
                    <Badge tone={row.status === 'hai' ? 'blue' : 'slate'}>{row.status === 'hai' ? 'Hai' : 'Kimya'}</Badge>
                  )}
                </div>
              </button>
              {open && (
                <div className="mt-3 space-y-2 border-t border-slate-200 pt-3 text-sm text-ink-800">
                  <p>Kifurushi: {row.last_package ?? '—'}</p>
                  <p>Malipo: {row.paid_via === 'kadi' ? 'kadi' : row.paid_via === 'simu' ? 'simu (Lipia)' : '—'}</p>
                  <p>MAC: {row.last_mac ?? '—'}</p>
                  <p>Mara ya kwanza: {ago(row.first_seen_at)}</p>
                  <div className="flex flex-wrap gap-2 pt-1">
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
                </div>
              )}
            </Card>
          );
        })}
      </div>
      {rows.length === 0 && (
        <Empty>
          {customers.isLoading
            ? 'Inapakia…'
            : tab === 'online'
              ? 'Hakuna mteja mtandaoni sasa hivi.'
              : tab === 'hai'
                ? 'Hakuna mteja Hai.'
                : 'Hakuna mteja Kimya.'}
        </Empty>
      )}
    </div>
  );
}
