import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import { PageHeader } from '../components/ui';
import { money } from '../format';
import { usePrefs } from '../preferences';

export function BillingPage() {
  const { sw } = usePrefs();
  const summary = useQuery({ queryKey: ['billing-summary'], queryFn: api.billingSummary });
  const row = summary.data?.data;
  const status =
    row?.status === 'paid'
      ? sw('Imelipwa', 'Paid')
      : row?.status === 'issued'
        ? sw('Inasubiri', 'Waiting')
        : row?.status === 'new'
          ? sw('Haijalipwa', 'Unpaid')
          : (row?.status ?? '');

  return (
    <div>
      <PageHeader title={sw('Malipo ya mfumo', 'Platform billing')} />
      <div className="grid grid-cols-2 gap-3">
        <section className="rounded-2xl border border-slate-200 bg-white p-5">
          <p className="text-xs font-semibold tracking-wide text-ink-700 uppercase">{sw('Siku zilizobaki', 'Days left')}</p>
          <p className="mt-1 text-4xl font-bold text-ink-900">{row ? row.days_left : '—'}</p>
          <p className="mt-2 text-sm text-ink-700">30</p>
        </section>
        <section className="rounded-2xl border border-slate-200 bg-white p-5">
          <p className="text-xs font-semibold tracking-wide text-ink-700 uppercase">{sw('Jumla', 'Total')}</p>
          <p className="mt-1 text-2xl font-bold text-ink-900">
            {row?.amount_minor != null ? money(row.amount_minor, row.currency) : '—'}
          </p>
          <p className="mt-2 text-sm text-ink-700">{status}</p>
        </section>
      </div>
      <section className="mt-4 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="text-sm font-semibold text-ink-700">{sw('Jinsi ya kulipa', 'How to pay')}</h2>
        <p className="mt-3 text-lg font-semibold text-ink-900">{row?.payee_name || '—'}</p>
        <p className="text-ink-800">{row?.account_number || '—'}</p>
        {row?.instructions && <p className="mt-2 text-sm text-ink-700">{row.instructions}</p>}
      </section>
    </div>
  );
}
