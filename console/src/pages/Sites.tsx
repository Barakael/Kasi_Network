import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api, type Site } from '../api';
import { Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn } from '../components/ui';
import { usePrefs } from '../preferences';

export function SitesPage() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<number | null>(null);
  const [siteForm, setSiteForm] = useState({ name: '', abbreviation: '' });
  const [agentForm, setAgentForm] = useState({ name: '', phone: '' });
  const [password, setPassword] = useState<string | null>(null);

  const createSite = useMutation({
    mutationFn: () => api.createSite(siteForm),
    onSuccess: (body) => {
      setSiteForm({ name: '', abbreviation: '' });
      setSelected(body.data.id);
      void client.invalidateQueries({ queryKey: ['sites'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const createAgent = useMutation({
    mutationFn: () =>
      api.createAgent({
        name: agentForm.name,
        phone: agentForm.phone,
        site_ids: selected ? [selected] : [],
      }),
    onSuccess: (body) => {
      setPassword(body.password);
      setAgentForm({ name: '', phone: '' });
      void client.invalidateQueries({ queryKey: ['sites'] });
      void client.invalidateQueries({ queryKey: ['agents'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const rows = sites.data?.data ?? [];
  const current = rows.find((site) => site.id === selected) ?? null;

  return (
    <div>
      <PageHeader title={sw('Maeneo', 'Sites')} />
      <ErrorBanner message={error} />
      <div className="grid gap-4 lg:grid-cols-[16rem_1fr]">
        <div className="space-y-2">
          {rows.map((site) => (
            <button
              key={site.id}
              type="button"
              onClick={() => {
                setSelected(site.id);
                setPassword(null);
              }}
              className={`w-full rounded-2xl border p-4 text-left ${
                selected === site.id ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white'
              }`}
            >
              <p className="font-semibold text-ink-900">{site.name}</p>
              <p className="text-sm text-ink-700">{site.abbreviation || site.nas_identifier}</p>
            </button>
          ))}
          {!rows.length && <Empty>{sites.isLoading ? '…' : sw('Maeneo', 'Sites')}</Empty>}
          <form
            className="space-y-2 rounded-2xl border border-slate-200 bg-white p-4"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              setError(null);
              createSite.mutate();
            }}
          >
            <Field label={sw('Jina', 'Name')}>
              <input className={inputClass} value={siteForm.name} onChange={(e) => setSiteForm({ ...siteForm, name: e.target.value })} required />
            </Field>
            <Field label={sw('Kifupi', 'Abbreviation')}>
              <input
                className={inputClass}
                value={siteForm.abbreviation}
                onChange={(e) => setSiteForm({ ...siteForm, abbreviation: e.target.value.toUpperCase() })}
                maxLength={12}
                required
              />
            </Field>
            <button type="submit" className={primaryBtn} disabled={createSite.isPending}>
              {sw('Ongeza eneo', 'Add site')}
            </button>
          </form>
        </div>
        <SiteAgents
          site={current}
          agentForm={agentForm}
          setAgentForm={setAgentForm}
          password={password}
          onSubmit={(event) => {
            event.preventDefault();
            setError(null);
            createAgent.mutate();
          }}
          pending={createAgent.isPending}
        />
      </div>
    </div>
  );
}

function SiteAgents({
  site,
  agentForm,
  setAgentForm,
  password,
  onSubmit,
  pending,
}: {
  site: Site | null;
  agentForm: { name: string; phone: string };
  setAgentForm: (value: { name: string; phone: string }) => void;
  password: string | null;
  onSubmit: (event: FormEvent) => void;
  pending: boolean;
}) {
  const { sw } = usePrefs();

  if (!site) {
    return <p className="text-sm text-ink-700">{sw('Chagua eneo.', 'Choose a site.')}</p>;
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5">
      <h2 className="text-lg font-semibold text-ink-900">
        {site.name} <span className="text-sm font-medium text-ink-700">{site.abbreviation}</span>
      </h2>
      <ul className="mt-4 divide-y divide-slate-200">
        {(site.agents ?? []).map((agent) => (
          <li key={agent.id} className="flex items-center justify-between py-3">
            <span className="font-medium">{agent.name}</span>
            <span className="text-sm text-ink-700">{agent.phone ?? agent.email}</span>
          </li>
        ))}
      </ul>
      {password && (
        <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          {sw('Nenosiri', 'Password')}: <strong>{password}</strong>
        </p>
      )}
      <form className="mt-4 grid gap-3 sm:grid-cols-2" onSubmit={onSubmit}>
        <Field label={sw('Jina', 'Name')}>
          <input className={inputClass} value={agentForm.name} onChange={(e) => setAgentForm({ ...agentForm, name: e.target.value })} required />
        </Field>
        <Field label={sw('Simu', 'Phone')}>
          <input className={inputClass} value={agentForm.phone} onChange={(e) => setAgentForm({ ...agentForm, phone: e.target.value })} placeholder="07XXXXXXXX" required />
        </Field>
        <div className="sm:col-span-2">
          <button type="submit" className={primaryBtn} disabled={pending}>
            {sw('Ongeza wakala', 'Add agent')}
          </button>
        </div>
      </form>
    </section>
  );
}
