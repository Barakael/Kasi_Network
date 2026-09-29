import { useState } from 'react';
import { PasswordForm } from '../components/PasswordForm';
import { Card, PageHeader } from '../components/ui';
import { isPlatformAdmin, useAuth } from '../auth';

export function AccountPage() {
  const { user } = useAuth();
  const [done, setDone] = useState<string | null>(null);
  const platform = isPlatformAdmin(user);

  return (
    <div>
      <PageHeader
        title="Akaunti"
        subtitle={`${user?.name ?? ''} — badilisha nenosiri. Vifaa vingine vitatolewa nje.`}
      />
      {done && (
        <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
          {done}
        </p>
      )}
      <Card className="max-w-lg">
        <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Nenosiri</h2>
        <p className="mb-4 text-sm text-ink-700">
          {platform
            ? 'Super Admin password. Keep this off tenant tills.'
            : 'Nenosiri la System Administrator. Mawakala wana desk yao.'}
        </p>
        <PasswordForm onDone={setDone} />
      </Card>
    </div>
  );
}
