import { useState } from 'react';
import { useAgentDesk } from '../agent/desk';
import { openPrintSheet } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, inputClass, primaryBtn } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';
import { useQueryClient } from '@tanstack/react-query';

export function AgentPrintPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const client = useQueryClient();
  const { desk, isLoading, error } = useAgentDesk();
  const [printError, setPrintError] = useState<string | null>(null);
  const [qty, setQty] = useState<Record<number, number>>({});
  const batches = desk?.batches ?? [];
  const currency = user?.tenant?.currency ?? 'TZS';

  return (
    <div>
      <PageHeader
        title={sw('Vocha', 'Vouchers')}
        subtitle={sw('Pokea pesa, chapisha kadi, mpe mteja. Leo ni pesa mkononi — inajiandikisha unapouza, si mteja anapounganisha.', 'Take the money, print the card, hand it over. Today is cash in hand — it is recorded when you sell, not when the customer connects.')}
      />
      <ErrorBanner message={printError ?? error?.message ?? null} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2">
        <Card>
          <p className="text-sm text-ink-700">{sw('Kadi za kuuza', 'Cards to sell')}</p>
          <p className="mt-1 text-3xl font-bold text-ink-900">{desk?.remaining_cards ?? '—'}</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">{sw('Leo (mkononi)', 'Today (in hand)')}</p>
          <p className="mt-1 text-3xl font-bold text-ink-900">{desk ? money(desk.leo.kadi_minor, currency) : '—'}</p>
          <p className="mt-1 text-sm text-ink-700">
            {desk?.leo.kadi_count ?? 0} {sw('zilizouzwa', 'sold')} · {desk?.leo.used_count ?? 0} {sw('zimetumika', 'used')}
          </p>
        </Card>
      </div>
      {isLoading && !desk ? (
        <Empty>{sw('Inapakia stock…', 'Loading stock…')}</Empty>
      ) : (
        <div className="space-y-3">
          {batches.map((batch) => {
            const left = batch.printable_count ?? 0;
            const price = batch.plan?.price_minor ?? 0;
            const count = Math.min(Math.max(1, qty[batch.id] ?? 1), left || 1);
            return (
              <Card key={batch.id}>
                <div className="flex flex-wrap items-end justify-between gap-3">
                  <div>
                    <p className="text-lg font-semibold text-ink-900">{batch.plan?.name ?? batch.reference}</p>
                    <p className="mt-0.5 text-sm text-ink-700">
                      {price ? money(price, currency) : batch.reference} · {left} {sw('kadi zimebaki', 'cards left')}
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge tone={batch.is_printable ? 'green' : 'amber'}>{batch.status_label}</Badge>
                    {batch.is_printable && left > 0 && (
                      <>
                        <label className="text-sm font-medium text-ink-800">
                          {sw('Idadi', 'Quantity')}
                          <input
                            className={`${inputClass} mt-1 w-24`}
                            type="number"
                            min={1}
                            max={left}
                            value={count}
                            onChange={(e) => setQty({ ...qty, [batch.id]: Number(e.target.value) })}
                          />
                        </label>
                        <button
                          type="button"
                          className={primaryBtn}
                          onClick={() => {
                            setPrintError(null);
                            void openPrintSheet(batch.id, count)
                              .then(() => client.invalidateQueries({ queryKey: ['desk'] }))
                              .catch((err: Error) => setPrintError(err.message));
                          }}
                        >
                          {sw('Uza', 'Sell')} {count}
                        </button>
                      </>
                    )}
                  </div>
                </div>
              </Card>
            );
          })}
        </div>
      )}
      {!isLoading && batches.length === 0 && (
        <Empty>{sw('Msimamizi bado hajakupa vocha. Huwezi kuuza kadi hadi upewe stock kwenye eneo hili.', 'The administrator has not given you vouchers yet. You cannot sell cards until this site has stock.')}</Empty>
      )}
    </div>
  );
}
