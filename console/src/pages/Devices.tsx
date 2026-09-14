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
            <table className="w-full min-w-[36rem] text-left text-sm">
              <thead>
                <tr className="border-b text-ink-700">
                  <th className="py-2 pr-3">MAC</th>
                  <th className="py-2 pr-3">Vendor</th>
                  <th className="py-2 pr-3">Label</th>
                  <th className="py-2 pr-3">Voucher</th>
                  <th className="py-2 pr-3">Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {devices.data.data.map((device) => (
                  <tr key={device.id} className="border-b border-slate-100">
                    <td className="py-2 pr-3 font-mono text-xs">{device.mac}</td>
                    <td className="py-2 pr-3">{device.vendor || '—'}</td>
                    <td className="py-2 pr-3">{device.label || '—'}</td>
                    <td className="py-2 pr-3 font-mono">…{device.voucher_suffix}</td>
                    <td className="py-2 pr-3">
                      <Badge tone={device.status === 'active' ? 'green' : 'slate'}>{device.status}</Badge>
                    </td>
                    <td className="py-2 text-right">
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
