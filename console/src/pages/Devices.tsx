import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, Guide, PageHeader, secondaryBtn } from '../components/ui';
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

  const rows = devices.data?.data ?? [];

  return (
    <div>
      <PageHeader
        title="Vifaa"
        subtitle="MAC zinazoshiriki vocha moja. Revoke inakata kifaa, si mteja mzima — Kata iko kwenye Mtandaoni."
      />
      <ErrorBanner message={error} />
      {!devices.isLoading && rows.length === 0 && (
        <Guide
          title="Hakuna MAC zilizofungwa"
          body="Vifaa vinaonekana hapa mteja anapoongeza simu ya pili kwenye kadi. Orodha ya namba iko kwenye Wateja."
          to="/customers"
          cta="Wateja"
        />
      )}
      {devices.isLoading && <Empty>Inapakia vifaa…</Empty>}
      {rows.length > 0 && (
        <Card>
          <div className="overflow-x-auto">
            <table className="console-table">
              <thead>
                <tr>
                  <th>MAC</th>
                  <th>Vendor</th>
                  <th>Label</th>
                  <th>Vocha</th>
                  <th>Hali</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {rows.map((device) => (
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
        </Card>
      )}
    </div>
  );
}
