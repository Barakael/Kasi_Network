import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { isAgent, useAuth } from '../auth';
import { ErrorBanner, Field, inputClass, primaryBtn } from '../components/ui';

export function LoginPage() {
  const { user, ready, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (ready && user) {
    return <Navigate to={isAgent(user) ? '/print' : '/'} replace />;
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const signedIn = await login(email, password);
      navigate(isAgent(signedIn) ? '/print' : '/', { replace: true });
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="flex min-h-dvh items-center justify-center bg-slate-50 px-4">
      <form onSubmit={(e) => void onSubmit(e)} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-sm font-semibold text-brand-700">Kasi Network</p>
        <h1 className="mt-1 text-2xl font-bold text-ink-900">Sign in</h1>
        <p className="mt-1 mb-6 text-sm text-ink-700">Operator console for hotspots, vouchers and sessions.</p>
        <ErrorBanner message={error} />
        <div className="space-y-3">
          <Field label="Email">
            <input className={inputClass} type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
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
          <button type="submit" className={`${primaryBtn} w-full`} disabled={busy}>
            {busy ? 'Signing in…' : 'Sign in'}
          </button>
        </div>
      </form>
    </main>
  );
}
