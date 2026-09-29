import { useState } from 'react';
import { PasswordForm } from '../components/PasswordForm';
import { Card, PageHeader } from '../components/ui';
import { useAuth } from '../auth';

export function AgentAccountPage() {
  const { user } = useAuth();
  const [done, setDone] = useState<string | null>(null);

  return (
    <div>
      <PageHeader title="Akaunti" subtitle={`${user?.name ?? 'Wakala'} — badilisha nenosiri la desk.`} />
      {done && (
        <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
          {done}
        </p>
      )}
      <Card className="max-w-lg">
        <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Nenosiri</h2>
        <p className="mb-4 text-sm text-ink-700">
          Unapopewa nenosiri la mara moja, libadilishe hapa. Vifaa vingine vitatolewa nje.
        </p>
        <PasswordForm onDone={setDone} />
      </Card>
    </div>
  );
}
