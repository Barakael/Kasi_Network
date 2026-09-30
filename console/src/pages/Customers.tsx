import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, type Customer } from '../api';
import { Badge, Callout, Empty, ErrorBanner, PageHeader, inputClass, secondaryBtn } from '../components/ui';
import { usePrefs } from '../preferences';

type Filter = 'all' | 'online' | 'paid' | 'expired';

const filters: Filter[] = ['all', 'online', 'paid', 'expired'];

function filterLabel(key: Filter, sw: (kiswahili: string, english: string) => string, compact = false): string {
  if (key === 'all') return sw('Wote', 'All');
  if (key === 'online') return compact ? sw('Mtandaoni', 'Online') : sw('Walio mtandaoni', 'Online now');
  if (key === 'paid') return compact ? sw('Walilipa', 'Paid') : sw('Waliolipa', 'Paid');
  return compact ? sw('Imeisha', 'Expired') : sw('Muda umeisha', 'Expired');
}

function statusLabel(row: Customer, sw: (kiswahili: string, english: string) => string): string {
  if (row.session_id) {
    return sw('Walio mtandaoni', 'Online now');
  }
  if (row.payment === 'paid') {
    return sw('Waliolipa', 'Paid');
  }
  if (row.payment === 'expired') {
    return sw('Muda umeisha', 'Expired');
  }
  return sw('Kimya', 'Quiet');
}

export function CustomersPage() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const [filter, setFilter] = useState<Filter>('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [code, setCode] = useState<string | null>(null);
  const customers = useQuery({
    queryKey: ['customers', filter, search, page],
    queryFn: () => api.customers(filter, undefined, search || undefined, page),
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

  const counts = customers.data?.counts;
  const rows = customers.data?.data ?? [];
  const lastPage = customers.data?.meta?.last_page ?? 1;

  return (
    <div>
      <PageHeader title={sw('Wateja', 'Customers')} />
      <ErrorBanner message={error} />
      {code && (
        <Callout tone="amber">
          <strong>{code}</strong>
        </Callout>
      )}
      <input
        className={`${inputClass} mb-3 w-full md:hidden`}
        value={search}
        onChange={(e) => {
          setSearch(e.target.value);
          setPage(1);
        }}
        placeholder={sw('Tafuta simu', 'Search phone')}
        aria-label={sw('Tafuta simu', 'Search phone')}
      />
      <div className="mb-3 grid grid-cols-4 gap-1 md:hidden" role="tablist">
        {filters.map((key) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={filter === key}
            onClick={() => {
              setFilter(key);
              setPage(1);
            }}
            className={`rounded-xl px-1 py-2 text-center ${
              filter === key ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-ink-800'
            }`}
          >
            <span className="block text-base font-bold leading-none">{counts ? counts[key] : '—'}</span>
            <span className="mt-1 block text-[10px] font-semibold leading-tight">{filterLabel(key, sw, true)}</span>
          </button>
        ))}
      </div>
      <div className="mb-4 hidden grid-cols-2 gap-3 md:grid lg:grid-cols-4">
        {filters.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => {
              setFilter(key);
              setPage(1);
            }}
            className={`rounded-2xl border p-4 text-left shadow-sm ${
              filter === key ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white'
            }`}
          >
            <p className="text-xs font-semibold tracking-wide text-ink-700 uppercase">{filterLabel(key, sw)}</p>
            <p className="mt-1 text-2xl font-bold text-ink-900">{counts ? counts[key] : '—'}</p>
          </button>
        ))}
      </div>
      <input
        className={`${inputClass} mb-4 hidden max-w-xs md:block`}
        value={search}
        onChange={(e) => {
          setSearch(e.target.value);
          setPage(1);
        }}
        placeholder={sw('Tafuta simu', 'Search phone')}
        aria-label={sw('Tafuta simu', 'Search phone')}
      />
      <div className="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4">
        <table className="console-table table-fixed md:table-auto">
          <thead>
            <tr>
              <th>{sw('Simu', 'Phone')}</th>
              <th>{sw('Hali', 'Status')}</th>
              <th className="hidden sm:table-cell">{sw('Kifurushi', 'Package')}</th>
              <th className="hidden md:table-cell">{sw('Eneo', 'Site')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td className="font-semibold text-ink-900">{row.phone_local || row.phone || sw('Inasubiri namba', 'Waiting for a number')}</td>
                <td>
                  <Badge tone={row.session_id ? 'green' : row.payment === 'expired' ? 'amber' : 'blue'}>
                    {statusLabel(row, sw)}
                  </Badge>
                </td>
                <td className="hidden sm:table-cell text-ink-700">{row.last_package ?? '—'}</td>
                <td className="hidden md:table-cell">
                  <div className="flex flex-wrap gap-2">
                    <span className="text-ink-700">{row.site?.name ?? '—'}</span>
                    {row.has_unused_voucher && (
                      <button type="button" className={secondaryBtn} onClick={() => reveal.mutate(row.id)}>
                        {sw('Vocha', 'Vouchers')}
                      </button>
                    )}
                    {row.session_id && (
                      <button type="button" className={secondaryBtn} onClick={() => disconnect.mutate(row.session_id as string)}>
                        {sw('Kata', 'Disconnect')}
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {!rows.length && <Empty>{customers.isLoading ? '…' : sw('Wateja', 'Customers')}</Empty>}
      </div>
      <div className="mt-3 flex items-center justify-between text-sm">
        <button type="button" className={secondaryBtn} disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
          ←
        </button>
        <span className="text-ink-700">
          {page} / {lastPage}
        </span>
        <button type="button" className={secondaryBtn} disabled={page >= lastPage} onClick={() => setPage((value) => value + 1)}>
          →
        </button>
      </div>
    </div>
  );
}
