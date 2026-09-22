import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, secondaryBtn } from '../components/ui';
import { useState } from 'react';

export function DevicesPage() {
  const client = useQueryClient();
  const devices = useQuery({ queryKey: ['devices'], queryFn: api.devices });
  const [error, setError] = useState<string | null>(null);
  const revoke = useMutation({
    mutationFn: api.revokeDevice,
    onSuccess: () => void client.invalidateQueries({ queryKey: ['devices'] }),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title="Bound devices" subtitle="MACs that share a parent voucher’s quota." />
      <ErrorBanner message={error} />
      <Card>
        {devices.data?.data.length ? (
          <div className="overflow-x-auto">
            <table className="console-table">
              <thead>
                <tr>
                  <th>MAC</th>
                  <th>Vendor</th>
                  <th>Label</th>
                  <th>Voucher</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {devices.data.data.map((device) => (
                  <tr key={device.id}>
                    <td className="font-mono text-xs">{device.mac}</td>
                    <td>{device.vendor || '—'}</td>
                    <td>{device.label || '—'}</td>
                    <td className="font-mono">…{device.voucher_suffix}</td>
                    <td>
                      <Badge tone={device.status === 'active' ? 'green' : 'slate'}>{device.status}</Badge>
                    </td>
                    <td className="text-right">
                      {device.status === 'active' && (
                        <button type="button" className={secondaryBtn} onClick={() => revoke.mutate(device.id)}>
                          Revoke
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty>{devices.isLoading ? 'Loading devices…' : 'No bound devices yet.'}</Empty>
        )}
      </Card>
    </div>
  );
}
