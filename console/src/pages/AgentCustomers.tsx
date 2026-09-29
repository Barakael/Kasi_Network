import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useAgentDesk } from '../agent/desk';
import { api, type Customer } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';

export function AgentCustomersPage() {
  const client = useQueryClient();
  const { desk, isLoading, error } = useAgentDesk();
  const [tab, setTab] = useState<'hai' | 'kimya'>('hai');
  const [query, setQuery] = useState('');
  const [openId, setOpenId] = useState<number | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const rows = (desk?.customers ?? []).filter((row) => {
    if (row.status !== tab) {
      return false;
    }
    const needle = query.trim().toLowerCase();
    if (!needle) {
      return true;
    }
    return (
      row.phone.toLowerCase().includes(needle) ||
      (row.phone_local ?? '').toLowerCase().includes(needle) ||
      (row.last_mac ?? '').toLowerCase().includes(needle)
    );
  });

  const disconnect = useMutation({
    mutationFn: api.disconnect,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['desk'] }),
    onError: (err: Error) => setLocalError(err.message),
  });

  return (
    <div>
      <PageHeader
        title="Wateja"
        subtitle={`Namba zilizonaswa kwenye ${desk?.site.name ?? 'site hii'} baada ya simu kwenye portal.`}
      />
      <ErrorBanner message={localError ?? error?.message ?? null} />
      <div className="mb-4 flex flex-wrap gap-2">
        <button type="button" className={tab === 'hai' ? primaryBtn : secondaryBtn} onClick={() => setTab('hai')}>
          Hai ({desk?.hai_count ?? 0})
        </button>
        <button type="button" className={tab === 'kimya' ? primaryBtn : secondaryBtn} onClick={() => setTab('kimya')}>
          Kimya ({desk?.kimya_count ?? 0})
        </button>
        <input
          className={`${inputClass} max-w-xs`}
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Tafuta 07… au MAC"
          aria-label="Tafuta mteja"
        />
      </div>
      {isLoading && !desk ? (
        <Empty>Inapakia wateja…</Empty>
      ) : (
        <div className="space-y-3">
          {rows.map((row: Customer) => {
            const open = openId === row.id;
            return (
              <Card key={row.id}>
                <button
                  type="button"
                  className="flex w-full flex-wrap items-center justify-between gap-2 text-left"
                  onClick={() => setOpenId(open ? null : row.id)}
                >
                  <div>
                    <p className="font-semibold text-ink-900">{row.phone}</p>
                    <p className="text-sm text-ink-700">{row.last_package ?? 'Bado hajanunua'}</p>
                  </div>
                  <Badge tone={row.status === 'hai' ? 'green' : 'slate'}>{row.status === 'hai' ? 'Hai' : 'Kimya'}</Badge>
                </button>
                {open && (
                  <div className="mt-3 space-y-2 border-t border-slate-200 pt-3 text-sm text-ink-800">
                    <p>Kifurushi: {row.last_package ?? '—'}</p>
                    <p>Malipo: {row.paid_via === 'kadi' ? 'kadi' : row.paid_via === 'simu' ? 'simu' : '—'}</p>
                    {row.has_unused_voucher && <p>Ana vocha aliyolipia, bado haijatumika.</p>}
                    {row.session_id && (
                      <button
                        type="button"
                        className={secondaryBtn}
                        onClick={() => {
                          setLocalError(null);
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
      )}
      {!isLoading && rows.length === 0 && (
        <Empty>{tab === 'hai' ? 'Hakuna mteja Hai kwenye site hii.' : 'Hakuna mteja Kimya kwenye site hii.'}</Empty>
      )}
    </div>
  );
}
