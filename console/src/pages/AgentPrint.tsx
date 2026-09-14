import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api, openPrintSheet } from '../api';
import { Badge, Card, Empty, ErrorBanner, PageHeader, primaryBtn } from '../components/ui';

export function AgentPrintPage() {
  const batches = useQuery({ queryKey: ['batches'], queryFn: api.batches, refetchInterval: 15_000 });
  const [error, setError] = useState<string | null>(null);

  return (
    <div>
      <PageHeader title="Print stock" subtitle="Only the batches assigned to you. Codes never appear in this list." />
      <ErrorBanner message={error} />
      <div className="space-y-3">
        {(batches.data?.data ?? []).map((batch) => (
          <Card key={batch.id}>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="font-semibold">{batch.reference}</p>
                <p className="text-sm text-ink-700">
                  {batch.plan?.name} · {batch.quantity} cards
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={batch.is_printable ? 'green' : 'amber'}>{batch.status_label}</Badge>
                {batch.is_printable && (
                  <button
                    type="button"
                    className={primaryBtn}
                    onClick={() => {
                      setError(null);
                      void openPrintSheet(batch.id).catch((err: Error) => setError(err.message));
                    }}
                  >
                    Print
                  </button>
                )}
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!batches.data?.data.length && <Empty>{batches.isLoading ? 'Loading your stock…' : 'No batches have been assigned to you yet.'}</Empty>}
    </div>
  );
}
