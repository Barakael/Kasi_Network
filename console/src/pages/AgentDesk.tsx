import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, openPrintSheet } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { clock, money } from '../format';
import { useAuth } from '../auth';

export function AgentDeskPage() {
  const { user } = useAuth();
  const client = useQueryClient();
  const [siteId, setSiteId] = useState<number | undefined>(undefined);
  const [error, setError] = useState<string | null>(null);
  const desk = useQuery({
    queryKey: ['desk', siteId],
    queryFn: () => api.agentDesk(siteId),
    refetchInterval: 15_000,
  });

  const disconnect = useMutation({
    mutationFn: api.disconnect,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['desk'] }),
    onError: (err: Error) => setError(err.message),
  });

  const data = desk.data;
  const currency = user?.tenant?.currency ?? 'TZS';

  return (
    <div>
      <PageHeader title={data?.site.name ?? 'Desk'} subtitle="Chapisha, leo, mtandaoni, wateja wa site hii." />
      <ErrorBanner message={error} />
      {(data?.sites.length ?? 0) > 1 && (
        <select
          className={`${inputClass} mb-4 max-w-xs`}
          value={data?.site.id}
          onChange={(e) => setSiteId(Number(e.target.value))}
        >
          {data?.sites.map((site) => (
            <option key={site.id} value={site.id}>
              {site.name}
            </option>
          ))}
        </select>
      )}
      <div className="grid gap-3 sm:grid-cols-3">
        <Card>
          <p className="text-sm text-ink-700">Chapisha</p>
          <p className="mt-1 text-2xl font-bold">{data?.remaining_cards ?? '—'}</p>
          <p className="text-sm text-ink-700">cards left</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">Leo</p>
          <p className="mt-1 text-2xl font-bold">{data ? money(data.leo.kadi_minor, currency) : '—'}</p>
          <p className="text-sm text-ink-700">{data?.leo.kadi_count ?? 0} cards used</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-700">Mtandaoni</p>
          <p className="mt-1 text-2xl font-bold">{data?.online.length ?? 0}</p>
        </Card>
      </div>
      <h2 className="mt-6 mb-2 text-sm font-semibold tracking-wide text-ink-700 uppercase">Chapisha</h2>
      <div className="space-y-3">
        {(data?.batches ?? []).map((batch) => (
          <Card key={batch.id}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{batch.reference}</p>
                <p className="text-sm text-ink-700">
                  {batch.plan?.name} · {batch.printable_count ?? 0} left
                </p>
              </div>
              {batch.is_printable && (
                <button
                  type="button"
                  className={primaryBtn}
                  onClick={() => {
                    setError(null);
                    void openPrintSheet(batch.id, batch.printable_count).catch((err: Error) => setError(err.message));
                  }}
                >
                  Chapisha
                </button>
              )}
            </div>
          </Card>
        ))}
      </div>
      <h2 className="mt-6 mb-2 text-sm font-semibold tracking-wide text-ink-700 uppercase">Mtandaoni</h2>
      <ul className="space-y-2">
        {(data?.online ?? []).map((session) => (
          <li key={session.acctuniqueid} className="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <span className="font-mono text-sm">{session.callingstationid || session.username.slice(-6)}</span>
            <span className="text-sm text-ink-700">{clock(session.seconds)}</span>
            <button type="button" className={secondaryBtn} onClick={() => disconnect.mutate(session.acctuniqueid)}>
              Kata
            </button>
          </li>
        ))}
        {(data?.online.length ?? 0) === 0 && <Empty>Hakuna aliye mtandaoni.</Empty>}
      </ul>
      <h2 className="mt-6 mb-2 text-sm font-semibold tracking-wide text-ink-700 uppercase">Wateja</h2>
      <div className="space-y-2">
        {(data?.customers ?? []).map((row) => (
          <Card key={row.id}>
            <div className="flex items-center justify-between">
              <span>{row.phone}</span>
              <Badge tone={row.status === 'hai' ? 'green' : 'slate'}>{row.status === 'hai' ? 'Hai' : 'Kimya'}</Badge>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
