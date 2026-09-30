import { useState } from 'react';
import { PasswordForm } from '../components/PasswordForm';
import { Card, PageHeader } from '../components/ui';
import { isPlatformAdmin, useAuth } from '../auth';
import { usePrefs } from '../preferences';

export function AccountPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const [done, setDone] = useState<string | null>(null);
  const platform = isPlatformAdmin(user);

  return (
    <div>
      <PageHeader
        title={sw('Akaunti', 'Account')}
        subtitle={`${user?.name ?? ''} — ${sw('badilisha nenosiri. Vifaa vingine vitatolewa nje.', 'change the password. Other devices will be signed out.')}`}
      />
      {done && (
        <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
          {done}
        </p>
      )}
      <Card className="max-w-lg">
        <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('Nenosiri', 'Password')}</h2>
        <p className="mb-4 text-sm text-ink-700">
          {platform
            ? sw('Nenosiri la Super Admin. Lisiingie kwenye makusanyo ya eneo.', 'Super Admin password. Keep this off tenant tills.')
            : sw('Nenosiri la msimamizi. Mawakala wana desk yao.', 'Administrator password. Agents have their own desk.')}
        </p>
        <PasswordForm onDone={setDone} />
      </Card>
    </div>
  );
}
