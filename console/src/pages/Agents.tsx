import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api } from '../api';
import { Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn } from '../components/ui';

export function AgentsPage() {
  const client = useQueryClient();
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const [error, setError] = useState<string | null>(null);
  const [password, setPassword] = useState<string | null>(null);
  const [form, setForm] = useState({ name: '', email: '', phone: '', site_id: '' });

  const create = useMutation({
    mutationFn: () =>
      api.createAgent({
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        site_ids: form.site_id ? [Number(form.site_id)] : [],
      }),
    onSuccess: (body) => {
      setPassword(body.password);
      setForm({ name: '', email: '', phone: '', site_id: '' });
      void client.invalidateQueries({ queryKey: ['agents'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title="Mawakala" subtitle="Invite an agent and pin them to a site. No site, no selling." />
      <ErrorBanner message={error} />
      {password && (
        <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          One-time password: <strong>{password}</strong>
        </p>
      )}
      <Card className="mb-4">
        <form
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            setError(null);
            create.mutate();
          }}
        >
          <Field label="Name">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label="Email">
            <input className={inputClass} type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
          </Field>
          <Field label="Phone">
            <input className={inputClass} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          </Field>
          <Field label="Site">
            <select className={inputClass} value={form.site_id} onChange={(e) => setForm({ ...form, site_id: e.target.value })} required>
              <option value="">Choose a site</option>
              {(sites.data?.data ?? []).map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </select>
          </Field>
          <button type="submit" className={primaryBtn} disabled={create.isPending}>
            Invite agent
          </button>
        </form>
      </Card>
      <div className="space-y-3">
        {(agents.data?.data ?? []).map((agent) => (
          <Card key={agent.id}>
            <p className="font-semibold">{agent.name}</p>
            <p className="text-sm text-ink-700">{agent.email}</p>
            <p className="mt-1 text-sm text-ink-700">
              {(agent.sites ?? []).map((site) => site.name).join(', ') || 'No site — cannot sell'}
            </p>
            <select
              className={`${inputClass} mt-2 max-w-xs`}
              value={agent.site_ids?.[0] ?? ''}
              onChange={(e) => {
                const id = Number(e.target.value);
                setError(null);
                void api
                  .updateAgent(agent.id, { site_ids: id ? [id] : [] })
                  .then(() => client.invalidateQueries({ queryKey: ['agents'] }))
                  .catch((err: Error) => setError(err.message));
              }}
            >
              <option value="">No site</option>
              {(sites.data?.data ?? []).map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </select>
          </Card>
        ))}
      </div>
      {!agents.data?.data.length && <Empty>{agents.isLoading ? 'Loading…' : 'No agents yet.'}</Empty>}
    </div>
  );
}
