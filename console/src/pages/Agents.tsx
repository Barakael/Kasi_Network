import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api, type User } from '../api';
import { Badge, Callout, Card, Empty, ErrorBanner, Field, Guide, PageHeader, inputClass, primaryBtn } from '../components/ui';

export function AgentsPage() {
  const client = useQueryClient();
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const batches = useQuery({ queryKey: ['batches'], queryFn: api.batches });
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
      void client.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const siteCount = sites.data?.data.length ?? 0;
  const rows = [...(agents.data?.data ?? [])].sort(
    (a, b) => Number((a.sites ?? []).length > 0) - Number((b.sites ?? []).length > 0),
  );
  const withoutSite = rows.filter((agent) => !(agent.sites ?? []).length).length;

  function stockFor(agent: User) {
    return (batches.data?.data ?? [])
      .filter((batch) => batch.assigned_agent?.id === agent.id && batch.status !== 'disabled')
      .reduce((sum, batch) => sum + (batch.printable_count ?? 0), 0);
  }

  return (
    <div>
      <PageHeader
        title="Mawakala"
        subtitle="Mwalike, pin kwa site, kisha mpe pack. Bila site, desk yake hawezi kuuza."
      />
      <ErrorBanner message={error} />
      {sites.isSuccess && siteCount === 0 && (
        <Guide title="Weka site kwanza" body="Wakala anauza kwenye shop moja. Tengeneza site kabla ya kumwalika." to="/sites" cta="Add site" />
      )}
      {password && (
        <Callout tone="amber">
          Nenosiri la mara moja: <strong>{password}</strong> — mpe wakala sasa, haitarudi.
        </Callout>
      )}
      {withoutSite > 0 && (
        <Callout tone="brand">
          {withoutSite} wakala bila site. Chagua shop kwenye orodha hapa chini, kisha{' '}
          <Link to="/batches" className="font-semibold underline">
            mpe pack
          </Link>
          .
        </Callout>
      )}
      {siteCount > 0 && (
        <Card className="mb-4">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Mwalike wakala</h2>
          <form
            className="grid gap-3 sm:grid-cols-2"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              setError(null);
              create.mutate();
            }}
          >
            <Field label="Jina">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </Field>
            <Field label="Barua pepe">
              <input className={inputClass} type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
            </Field>
            <Field label="Simu">
              <input className={inputClass} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
            </Field>
            <Field label="Site">
              <select className={inputClass} value={form.site_id} onChange={(e) => setForm({ ...form, site_id: e.target.value })} required>
                <option value="">Chagua site…</option>
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
      )}
      <div className="space-y-3">
        {rows.map((agent) => {
          const sitesLabel = (agent.sites ?? []).map((site) => site.name).join(', ');
          const cards = stockFor(agent);
          return (
            <Card key={agent.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-semibold text-ink-900">{agent.name}</p>
                  <p className="text-sm text-ink-700">{agent.email}</p>
                  <p className="mt-1 text-sm text-ink-700">{sitesLabel || 'Hakuna site — hawezi kuuza'}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <Badge tone={sitesLabel ? 'green' : 'amber'}>{sitesLabel ? 'Tayari' : 'Bila site'}</Badge>
                  <Badge tone={cards > 0 ? 'green' : 'slate'}>{cards} kadi</Badge>
                </div>
              </div>
              <div className="mt-3 flex flex-wrap items-end gap-2">
                <label className="block text-sm font-medium text-ink-800">
                  Site
                  <select
                    className={`${inputClass} mt-1 min-w-48`}
                    value={agent.site_ids?.[0] ?? agent.sites?.[0]?.id ?? ''}
                    onChange={(e) => {
                      const id = Number(e.target.value);
                      setError(null);
                      void api
                        .updateAgent(agent.id, { site_ids: id ? [id] : [] })
                        .then(() => client.invalidateQueries({ queryKey: ['agents'] }))
                        .catch((err: Error) => setError(err.message));
                    }}
                  >
                    <option value="">Hakuna site</option>
                    {(sites.data?.data ?? []).map((site) => (
                      <option key={site.id} value={site.id}>
                        {site.name}
                      </option>
                    ))}
                  </select>
                </label>
                {sitesLabel && cards === 0 && (
                  <Link to="/batches" className={`${primaryBtn} no-underline`}>
                    Mpe pack
                  </Link>
                )}
              </div>
            </Card>
          );
        })}
      </div>
      {!agents.data?.data.length && <Empty>{agents.isLoading ? 'Inapakia…' : 'Hakuna wakala bado.'}</Empty>}
    </div>
  );
}
