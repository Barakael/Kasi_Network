import { useState } from 'react';
import { PasswordForm } from '../components/PasswordForm';
import { Card, PageHeader } from '../components/ui';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

export function AgentAccountPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const [done, setDone] = useState<string | null>(null);

  return (
    <div>
      <PageHeader title={sw('Akaunti', 'Account')} subtitle={`${user?.name ?? sw('Wakala', 'Agent')} — ${sw('badilisha nenosiri la desk.', 'change the desk password.')}`} />
      {done && (
        <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
          {done}
        </p>
      )}
      <Card className="max-w-lg">
        <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Nenosiri', 'Password')}</h2>
        <p className="mb-4 text-sm text-ink-700">
          {sw('Unapopewa nenosiri la mara moja, libadilishe hapa. Vifaa vingine vitatolewa nje.', 'When you are given a one-time password, change it here. Other devices will be signed out.')}
        </p>
        <PasswordForm onDone={setDone} />
      </Card>
    </div>
  );
}
