import { useState, type FormEvent } from 'react';
import { api } from '../api';
import { Field, inputClass, primaryBtn } from './ui';

export function PasswordForm({ onDone }: { onDone?: (message: string) => void }) {
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const result = await api.changePassword(currentPassword, password, confirmation);
      setCurrentPassword('');
      setPassword('');
      setConfirmation('');
      onDone?.(result.message);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="space-y-3" onSubmit={(e) => void onSubmit(e)}>
      {error && (
        <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
          {error}
        </p>
      )}
      <Field label="Nenosiri la sasa">
        <input
          className={inputClass}
          type="password"
          autoComplete="current-password"
          value={currentPassword}
          onChange={(e) => setCurrentPassword(e.target.value)}
          required
        />
      </Field>
      <Field label="Nenosiri jipya (angalau herufi 10)">
        <input
          className={inputClass}
          type="password"
          autoComplete="new-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          minLength={10}
          required
        />
      </Field>
      <Field label="Thibitisha nenosiri">
        <input
          className={inputClass}
          type="password"
          autoComplete="new-password"
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
          minLength={10}
          required
        />
      </Field>
      <button type="submit" className={primaryBtn} disabled={busy}>
        {busy ? 'Subiri…' : 'Badilisha nenosiri'}
      </button>
    </form>
  );
}
