import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { useAuth } from '../auth';
import { ErrorBanner, Field, inputClass, primaryBtn } from '../components/ui';

function homeFor(role: string | undefined): string {
  if (role === 'agent') {
    return '/desk';
  }
  if (role === 'platform_admin') {
    return '/platform';
  }
  return '/';
}

export function LoginPage() {
  const { user, ready, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactor, setTwoFactor] = useState('');
  const [needTwoFactor, setNeedTwoFactor] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (ready && user) {
    return <Navigate to={homeFor(user.role)} replace />;
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const signedIn = await login(email, password, twoFactor || undefined);
      navigate(homeFor(signedIn.role), { replace: true });
    } catch (err) {
      const failed = err as Error & { requiresTwoFactor?: boolean };
      if (failed.requiresTwoFactor) {
        setNeedTwoFactor(true);
        setError('Weka namba kutoka kwenye authenticator.');
      } else {
        setError(failed.message);
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="flex min-h-dvh items-center justify-center bg-slate-50 px-4 py-10">
      <form
        onSubmit={(e) => void onSubmit(e)}
        className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"
      >
        <p className="text-sm font-semibold text-brand-700">Kasi Network</p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-ink-900">Sign in</h1>
        <p className="mt-1 mb-6 text-sm text-ink-700">Console for System Administrators, agents and Super Admin.</p>
        <ErrorBanner message={error} />
        <div className="space-y-3">
          <Field label="Email">
            <input
              className={inputClass}
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </Field>
          <Field label="Password">
            <input
              className={inputClass}
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </Field>
          {needTwoFactor && (
            <Field label="Authenticator code">
              <input
                className={inputClass}
                inputMode="numeric"
                autoComplete="one-time-code"
                value={twoFactor}
                onChange={(e) => setTwoFactor(e.target.value)}
                required
              />
            </Field>
          )}
          <button type="submit" className={`${primaryBtn} w-full`} disabled={busy}>
            {busy ? 'Signing in…' : 'Sign in'}
          </button>
        </div>
      </form>
    </main>
  );
}
