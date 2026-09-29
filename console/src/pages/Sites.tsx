import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Callout, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

export function SitesPage() {
  const { user } = useAuth();
  const client = useQueryClient();
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ name: '', ssid: '', nas_identifier: '' });
  const [openId, setOpenId] = useState<number | null>(null);
  const detail = useQuery({
    queryKey: ['site', openId],
    queryFn: () => api.site(openId as number),
    enabled: openId !== null,
  });
  const currency = user?.tenant?.currency ?? 'TZS';

  const create = useMutation({
    mutationFn: () => api.createSite(form),
    onSuccess: () => {
      setForm({ name: '', ssid: '', nas_identifier: '' });
      void client.invalidateQueries({ queryKey: ['sites'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader title="Sites" subtitle="Shop: SSID, mawakala, na pack. Weka site kabla ya router au wakala." />
      <ErrorBanner message={error} />
      {sites.isSuccess && !(sites.data?.data.length) && (
        <Callout tone="brand">
          Portal site id ndiyo NAS-Identifier kwenye MikroTik. Baada ya site: ongeza router, kisha mwalike wakala.
        </Callout>
      )}
      <Card className="mb-4">
        <form
          className="grid gap-3 sm:grid-cols-3"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          <Field label="Name">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label="SSID">
            <input className={inputClass} value={form.ssid} onChange={(e) => setForm({ ...form, ssid: e.target.value })} />
          </Field>
          <Field label="Portal site id">
            <input className={inputClass} value={form.nas_identifier} onChange={(e) => setForm({ ...form, nas_identifier: e.target.value })} required />
          </Field>
          <div className="sm:col-span-3">
            <button type="submit" className={primaryBtn} disabled={create.isPending}>
              Add site
            </button>
          </div>
        </form>
      </Card>
      <div className="space-y-3">
        {(sites.data?.data ?? []).map((site) => (
          <Card key={site.id}>
            <div className="flex flex-wrap items-start justify-between gap-3">
              <button type="button" className="text-left" onClick={() => setOpenId(openId === site.id ? null : site.id)}>
                <p className="font-semibold">{site.name}</p>
                <p className="text-sm text-ink-700">
                  {site.ssid || 'Hakuna SSID'} · {site.nas_devices_count ?? 0} router
                  {(site.agents ?? []).length ? ` · ${(site.agents ?? []).length} wakala` : ' · hakuna wakala'}
                </p>
              </button>
              <label className="text-sm text-ink-800">
                Agents
                <select
                  className={`${inputClass} mt-1 min-w-48`}
                  multiple
                  value={(site.agents ?? []).map((a) => String(a.id))}
                  onChange={(e) => {
                    const ids = Array.from(e.target.selectedOptions).map((o) => Number(o.value));
                    setError(null);
                    void api
                      .updateSite(site.id, { agent_ids: ids })
                      .then(() => client.invalidateQueries({ queryKey: ['sites'] }))
                      .catch((err: Error) => setError(err.message));
                  }}
                >
                  {(agents.data?.data ?? []).map((agent) => (
                    <option key={agent.id} value={agent.id}>
                      {agent.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            {openId === site.id && detail.data && (
              <div className="mt-3 grid gap-2 border-t border-slate-200 pt-3 text-sm text-ink-800 sm:grid-cols-4">
                <p>Routers: {site.nas_devices_count ?? 0}</p>
                <p>Mtandaoni: {detail.data.online_count}</p>
                <p>Leo: {money(detail.data.today.total_minor, currency)}</p>
                <p>Kadi zilizobaki: {detail.data.remaining_cards}</p>
                {detail.data.router_quiet && <p className="sm:col-span-4 text-amber-800">Router kimya — RADIUS haijaona paketi.</p>}
                {(site.nas_devices_count ?? 0) === 0 && (
                  <p className="sm:col-span-4">
                    <Link to="/routers" className="font-semibold text-brand-700 hover:underline">
                      Ongeza router
                    </Link>
                  </p>
                )}
                {(site.agents ?? []).length === 0 && (
                  <p className="sm:col-span-4">
                    <Link to="/agents" className="font-semibold text-brand-700 hover:underline">
                      Pin wakala
                    </Link>
                  </p>
                )}
              </div>
            )}
          </Card>
        ))}
      </div>
      {!sites.data?.data.length && <Empty>{sites.isLoading ? 'Inapakia…' : 'Hakuna site bado — jaza fomu hapo juu.'}</Empty>}
    </div>
  );
}
