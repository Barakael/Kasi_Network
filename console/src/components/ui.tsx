import type { ReactNode } from 'react';
import { Link } from 'react-router';

export const inputClass =
  'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-ink-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';

export const primaryBtn =
  'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 font-semibold text-white hover:bg-brand-700 disabled:opacity-50';

export const secondaryBtn =
  'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 font-semibold text-ink-800 hover:bg-slate-50';

export const dangerBtn =
  'inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 font-semibold text-white hover:bg-red-700 disabled:opacity-50';

export function PageHeader({ title, subtitle, children }: { title: string; subtitle?: string; children?: ReactNode }) {
  return (
    <header className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">{title}</h1>
        {subtitle && <p className="mt-1 max-w-2xl text-sm text-ink-700">{subtitle}</p>}
      </div>
      {children}
    </header>
  );
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <section className={`rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-5 ${className}`}>{children}</section>
  );
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

export function Callout({ children, tone = 'slate' }: { children: ReactNode; tone?: 'slate' | 'amber' | 'brand' }) {
  const tones = {
    slate: 'border border-slate-200 bg-slate-50 text-ink-800',
    amber: 'bg-amber-50 text-amber-900',
    brand: 'bg-brand-50 text-brand-800',
  };

  return (
    <div className={`mb-4 rounded-lg px-3 py-2 text-sm ${tones[tone]}`} role="status">
      {children}
    </div>
  );
}

export function Segmented<T extends string>({
  value,
  options,
  onChange,
  label,
}: {
  value: T;
  options: { value: T; label: string }[];
  onChange: (value: T) => void;
  label: string;
}) {
  return (
    <div className="inline-flex rounded-xl border border-slate-200 bg-white p-1" role="group" aria-label={label}>
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          aria-pressed={value === option.value}
          className={`min-h-9 rounded-lg px-3 text-sm font-semibold transition ${
            value === option.value ? 'bg-brand-600 text-white' : 'text-ink-800 hover:bg-slate-50'
          }`}
          onClick={() => onChange(option.value)}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}

export function Delta({ pct, suffix = '' }: { pct?: number | null; suffix?: string }) {
  if (pct === null || pct === undefined) {
    return <span className="text-xs font-medium text-ink-700">Hakuna kulinganisha{suffix}</span>;
  }

  const tone = pct >= 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900';

  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>
      {pct >= 0 ? '+' : ''}
      {pct}%{suffix}
    </span>
  );
}

export function StatCard({
  label,
  value,
  hint,
  to,
  children,
}: {
  label: string;
  value: string | number;
  hint?: ReactNode;
  to?: string;
  children?: ReactNode;
}) {
  const body = (
    <section className="h-full rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm transition hover:border-brand-400 sm:p-4">
      <p className="text-[10px] font-semibold tracking-[0.06em] text-ink-700 uppercase sm:text-xs">{label}</p>
      <p className="mt-0.5 text-base font-bold leading-tight tracking-tight text-ink-900 sm:mt-1.5 sm:text-2xl">{value}</p>
      {hint && <p className="mt-0.5 text-[11px] leading-snug text-ink-700 sm:mt-1 sm:text-sm">{hint}</p>}
      {children && <div className="mt-2">{children}</div>}
    </section>
  );

  return to ? (
    <Link to={to} className="block h-full no-underline">
      {body}
    </Link>
  ) : (
    body
  );
}

export function Meter({ segments }: { segments: { label: string; value: number; className: string }[] }) {
  const total = segments.reduce((sum, segment) => sum + Math.max(0, segment.value), 0);

  return (
    <div>
      <div className="flex h-2.5 overflow-hidden rounded-full bg-slate-100">
        {total > 0 &&
          segments.map((segment) => (
            <span
              key={segment.label}
              className={segment.className}
              style={{ width: `${(Math.max(0, segment.value) / total) * 100}%` }}
            />
          ))}
      </div>
      <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        {segments.map((segment) => (
          <li key={segment.label} className="flex items-center gap-1.5 text-ink-700">
            <span className={`h-2.5 w-2.5 rounded-full ${segment.className}`} />
            {segment.label}
            <span className="font-semibold text-ink-900">{segment.value}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function Section({
  title,
  action,
  children,
  className = '',
}: {
  title: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Card className={className}>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">{title}</h2>
        {action}
      </div>
      {children}
    </Card>
  );
}

export function Guide({ title, body, to, cta }: { title: string; body: string; to: string; cta: string }) {
  return (
    <Card className="mb-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-semibold text-ink-900">{title}</p>
          <p className="text-sm text-ink-700">{body}</p>
        </div>
        <Link to={to} className={primaryBtn}>
          {cta}
        </Link>
      </div>
    </Card>
  );
}
