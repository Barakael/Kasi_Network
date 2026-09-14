import type { ReactNode } from 'react';

export const inputClass =
  'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-ink-900 outline-none focus:border-brand-500';

export const primaryBtn =
  'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 font-semibold text-white disabled:opacity-50';

export const secondaryBtn =
  'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 font-semibold text-ink-800';

export const dangerBtn =
  'inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 font-semibold text-white disabled:opacity-50';

export function PageHeader({ title, subtitle, children }: { title: string; subtitle?: string; children?: ReactNode }) {
  return (
    <header className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold text-ink-900">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-ink-700">{subtitle}</p>}
      </div>
      {children}
    </header>
  );
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ${className}`}>{children}</section>;
}

export function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-sm font-medium text-ink-800">
      {label}
      <div className="mt-1">{children}</div>
    </label>
  );
}

export function Badge({ children, tone = 'slate' }: { children: ReactNode; tone?: 'slate' | 'green' | 'amber' | 'red' | 'blue' }) {
  const tones = {
    slate: 'bg-slate-100 text-ink-800',
    green: 'bg-emerald-50 text-emerald-800',
    amber: 'bg-amber-50 text-amber-800',
    red: 'bg-red-50 text-red-800',
    blue: 'bg-brand-50 text-brand-700',
  };

  return <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${tones[tone]}`}>{children}</span>;
}

export function ErrorBanner({ message }: { message: string | null }) {
  if (!message) {
    return null;
  }

  return (
    <p className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
      {message}
    </p>
  );
}

export function Empty({ children }: { children: ReactNode }) {
  return <p className="py-8 text-center text-sm text-ink-700">{children}</p>;
}
