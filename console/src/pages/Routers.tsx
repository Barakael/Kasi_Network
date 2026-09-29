import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Badge, Callout, Card, Empty, ErrorBanner, Field, Guide, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';

export function RoutersPage() {
  const client = useQueryClient();
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const routers = useQuery({ queryKey: ['nas'], queryFn: api.nasDevices });
  const [error, setError] = useState<string | null>(null);
  const [snippet, setSnippet] = useState<{ name: string; rsc: string; login_html: string } | null>(null);
  const [nasForm, setNasForm] = useState({ site_id: '', name: '', nasname: '', api_host: '', shared_secret: '' });
  const [nasnameEdits, setNasnameEdits] = useState<Record<number, string>>({});

  const createNas = useMutation({
    mutationFn: () =>
      api.createNas({
        site_id: Number(nasForm.site_id),
        name: nasForm.name,
        nasname: nasForm.nasname,
        api_host: nasForm.api_host || null,
        shared_secret: nasForm.shared_secret || null,
      }),
    onSuccess: () => {
      setNasForm({ ...nasForm, name: '', nasname: '', api_host: '', shared_secret: '' });
      void client.invalidateQueries({ queryKey: ['nas'] });
      void client.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const updateNas = useMutation({
    mutationFn: ({ id, nasname }: { id: number; nasname: string }) => api.updateNas(id, { nasname }),
    onSuccess: () => void client.invalidateQueries({ queryKey: ['nas'] }),
    onError: (err: Error) => setError(err.message),
  });

  async function loadSnippet(id: number, name: string) {
    setError(null);
    try {
      const data = await api.snippet(id);
      setSnippet({ name, ...data });
    } catch (err) {
      setError((err as Error).message);
    }
  }

  function download(contents: string, filename: string) {
    const url = URL.createObjectURL(new Blob([contents], { type: 'text/plain' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  const siteCount = sites.data?.data.length ?? 0;
  const quietCount = (routers.data?.data ?? []).filter((row) => row.router_quiet).length;

  return (
    <div>
      <PageHeader title="Routers" subtitle="MikroTik moja kwa site. Pakua snippet + login.html, kisha RADIUS inaweza kuona simu." />
      <ErrorBanner message={error} />
      {sites.isSuccess && siteCount === 0 && (
        <Guide title="Weka site kwanza" body="Router inahitaji shop. Tengeneza site, kisha rudi hapa kuongeza MikroTik." to="/sites" cta="Add site" />
      )}
      {quietCount > 0 && (
        <Callout tone="amber">
          {quietCount} router {quietCount === 1 ? 'iko' : 'ziko'} kimya — RADIUS haijaona paketi. Angalia IP, shared secret, na snippet.
        </Callout>
      )}
      {siteCount > 0 && (
        <Card className="mb-4">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">MikroTik mpya</h2>
          <form
            className="grid gap-3 sm:grid-cols-2"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              createNas.mutate();
            }}
          >
            <Field label="Site">
              <select className={inputClass} value={nasForm.site_id} onChange={(e) => setNasForm({ ...nasForm, site_id: e.target.value })} required>
                <option value="">Chagua site…</option>
                {(sites.data?.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Jina">
              <input className={inputClass} value={nasForm.name} onChange={(e) => setNasForm({ ...nasForm, name: e.target.value })} required />
            </Field>
            <Field label="RADIUS source IP">
              <input
                className={inputClass}
                value={nasForm.nasname}
                onChange={(e) => setNasForm({ ...nasForm, nasname: e.target.value })}
                placeholder="Public IP FreeRADIUS inaona"
                required
              />
            </Field>
            <Field label="Hotspot LAN IP (si lazima)">
              <input
                className={inputClass}
                value={nasForm.api_host}
                onChange={(e) => setNasForm({ ...nasForm, api_host: e.target.value })}
                placeholder="192.168.10.212"
              />
            </Field>
            <Field label="Shared secret (wazi = generate)">
              <input className={inputClass} value={nasForm.shared_secret} onChange={(e) => setNasForm({ ...nasForm, shared_secret: e.target.value })} />
            </Field>
            <div className="flex items-end">
              <button type="submit" className={primaryBtn} disabled={createNas.isPending}>
                Save router
              </button>
            </div>
          </form>
        </Card>
      )}
      <div className="space-y-3">
        {(routers.data?.data ?? []).map((router) => (
          <Card key={router.id}>
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="min-w-0 flex-1">
                <p className="font-semibold">{router.name}</p>
                <p className="text-sm text-ink-700">
                  {router.nasname} · {router.site?.name}
                  {router.router_quiet ? ' · router kimya' : ''}
                </p>
                <form
                  className="mt-2 flex flex-wrap items-end gap-2"
                  onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    const nasname = (nasnameEdits[router.id] ?? router.nasname).trim();
                    if (!nasname || nasname === router.nasname) {
                      return;
                    }
                    setError(null);
                    updateNas.mutate({ id: router.id, nasname });
                  }}
                >
                  <Field label="RADIUS source IP">
                    <input
                      className={inputClass}
                      value={nasnameEdits[router.id] ?? router.nasname}
                      onChange={(e) => setNasnameEdits({ ...nasnameEdits, [router.id]: e.target.value })}
                    />
                  </Field>
                  <button type="submit" className={secondaryBtn} disabled={updateNas.isPending}>
                    Save IP
                  </button>
                </form>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={router.router_quiet ? 'amber' : router.status === 'active' ? 'green' : 'slate'}>
                  {router.router_quiet ? 'Kimya' : router.status}
                </Badge>
                <button type="button" className={secondaryBtn} onClick={() => void loadSnippet(router.id, router.name)}>
                  Snippet
                </button>
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!routers.data?.data.length && siteCount > 0 && (
        <Empty>{routers.isLoading ? 'Inapakia routers…' : 'Hakuna MikroTik bado — jaza fomu hapo juu.'}</Empty>
      )}
      {snippet && (
        <Card className="mt-4">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 className="font-semibold">Onboarding: {snippet.name}</h2>
            <div className="flex gap-2">
              <button type="button" className={primaryBtn} onClick={() => download(snippet.rsc, `${snippet.name}.rsc`)}>
                Download .rsc
              </button>
              <button type="button" className={secondaryBtn} onClick={() => download(snippet.login_html, 'login.html')}>
                login.html
              </button>
            </div>
          </div>
          <p className="mb-2 text-sm text-ink-700">
            Weka faili kwenye MikroTik, kisha rudi{' '}
            <Link to="/sessions" className="font-semibold text-brand-700 hover:underline">
              Mtandaoni
            </Link>{' '}
            kuona simu.
          </p>
          <pre className="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{snippet.rsc}</pre>
        </Card>
      )}
    </div>
  );
}
