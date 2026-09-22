import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, dangerBtn } from '../components/ui';
import { bytes, clock } from '../format';
import { useState } from 'react';

export function SessionsPage() {
  const client = useQueryClient();
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: api.sessions, refetchInterval: 10_000 });
  const [error, setError] = useState<string | null>(null);
  const disconnect = useMutation({
    mutationFn: api.disconnect,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['sessions'] }),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title="Sessions" subtitle="Kick a client off without waiting for their voucher to expire." />
      <ErrorBanner message={error} />
      <Card>
        {sessions.data?.data.length ? (
          <div className="overflow-x-auto">
            <table className="console-table min-w-[40rem]">
              <thead>
                <tr>
                  <th>MAC</th>
                  <th>IP</th>
                  <th>Router</th>
                  <th>Time</th>
                  <th>Data</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {sessions.data.data.map((session) => (
                  <tr key={session.acctuniqueid}>
                    <td className="font-mono text-xs">{session.callingstationid}</td>
                    <td>{session.framedipaddress}</td>
                    <td>{session.nasipaddress}</td>
                    <td>{clock(session.seconds)}</td>
                    <td>{bytes(session.bytes)}</td>
                    <td className="text-right">
                      <button
                        type="button"
                        className={dangerBtn}
                        disabled={disconnect.isPending}
                        onClick={() => disconnect.mutate(session.acctuniqueid)}
                      >
                        Disconnect
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty>
            {sessions.isLoading ? 'Loading sessions…' : 'No open sessions.'}
          </Empty>
        )}
      </Card>
      <p className="mt-3 text-xs text-ink-700">
        <Badge tone="blue">Live</Badge> Refreshes every 10 seconds.
      </p>
    </div>
  );
}
