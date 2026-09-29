import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useAgentDesk } from '../agent/desk';
import { api } from '../api';
import { Card, Empty, ErrorBanner, PageHeader, secondaryBtn } from '../components/ui';
import { clock } from '../format';

export function AgentOnlinePage() {
  const client = useQueryClient();
  const { desk, isLoading, error } = useAgentDesk();
  const [localError, setLocalError] = useState<string | null>(null);
  const online = desk?.online ?? [];

  const disconnect = useMutation({
    mutationFn: api.disconnect,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['desk'] }),
    onError: (err: Error) => setLocalError(err.message),
  });

  return (
    <div>
      <PageHeader
        title="Mtandaoni"
        subtitle={`Waliounganishwa kwenye ${desk?.site.name ?? 'site hii'}. Kata inakata session ya RADIUS.`}
      />
      <ErrorBanner message={localError ?? error?.message ?? null} />
      <Card className="mb-4">
        <p className="text-sm text-ink-700">Watu sasa</p>
        <p className="mt-1 text-3xl font-bold text-ink-900">{online.length}</p>
      </Card>
      {isLoading && !desk ? (
        <Empty>Inapakia…</Empty>
      ) : (
        <ul className="space-y-2">
          {online.map((session) => (
            <li
              key={session.acctuniqueid}
              className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3"
            >
              <div className="min-w-0">
                <p className="font-mono text-sm font-semibold text-ink-900">
                  {session.callingstationid || session.username.slice(-6)}
                </p>
                <p className="text-sm text-ink-700">
                  {session.framedipaddress || '—'} · {clock(session.seconds)}
                </p>
              </div>
              <button
                type="button"
                className={secondaryBtn}
                disabled={disconnect.isPending}
                onClick={() => {
                  setLocalError(null);
                  disconnect.mutate(session.acctuniqueid);
                }}
              >
                Kata
              </button>
            </li>
          ))}
        </ul>
      )}
      {!isLoading && online.length === 0 && <Empty>Hakuna aliye mtandaoni.</Empty>}
    </div>
  );
}
