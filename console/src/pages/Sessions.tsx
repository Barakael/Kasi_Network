import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../api';
import { Badge, Callout, Card, Empty, ErrorBanner, PageHeader, dangerBtn } from '../components/ui';
import { bytes, clock } from '../format';
import { useState } from 'react';

export function SessionsPage() {
  const client = useQueryClient();
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: api.sessions, refetchInterval: 10_000 });
  const dash = useQuery({ queryKey: ['dashboard'], queryFn: api.dashboard, refetchInterval: 15_000 });
  const [error, setError] = useState<string | null>(null);
  const disconnect = useMutation({
    mutationFn: api.disconnect,
    onSuccess: (result) => {
      void client.invalidateQueries({ queryKey: ['sessions'] });
      if (result.disconnected) {
        setError(null);
        return;
      }
      setError('Kasi imeondoa simu kwenye RADIUS. Kwenye MikroTik: /ip hotspot active remove [find]');
    },
    onError: (err: Error) => setError(err.message),
  });

  const empty = !(sessions.data?.data.length);
  const quiet = Boolean(dash.data?.router_quiet);

  return (
    <div>
      <PageHeader title="Mtandaoni" subtitle="Simu zilizo na session sasa. Kata inawaondoa bila kusubiri vocha iishe." />
      <ErrorBanner message={error} />
      {quiet && empty && (
        <Callout tone="amber">
          Router kimya — 0 mtandaoni inaweza kuwa radio, si wateja.{' '}
          <Link to="/routers" className="font-semibold underline">
            Routers
          </Link>
        </Callout>
      )}
      <Card>
        {sessions.data?.data.length ? (
          <div className="overflow-x-auto">
            <table className="console-table min-w-[40rem]">
              <thead>
                <tr>
                  <th>MAC</th>
                  <th>IP</th>
                  <th>Router</th>
                  <th>Muda</th>
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
                        Kata
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty>
            {sessions.isLoading ? (
              'Inapakia sessions…'
            ) : (
              <>
                Hakuna aliye mtandaoni.{' '}
                <Link to="/customers" className="font-semibold text-brand-700 hover:underline">
                  Wateja
                </Link>
              </>
            )}
          </Empty>
        )}
      </Card>
      <p className="mt-3 text-xs text-ink-700">
        <Badge tone="blue">Live</Badge> Inasasisha kila sekunde 10.
      </p>
    </div>
  );
}
