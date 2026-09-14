import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';

export function RoutersPage() {
  const client = useQueryClient();
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const routers = useQuery({ queryKey: ['nas'], queryFn: api.nasDevices });
  const [error, setError] = useState<string | null>(null);
  const [snippet, setSnippet] = useState<{ name: string; rsc: string; login_html: string } | null>(null);
  const [siteForm, setSiteForm] = useState({ name: '', ssid: '', nas_identifier: '' });
  const [nasForm, setNasForm] = useState({ site_id: '', name: '', nasname: '', shared_secret: '' });

  const createSite = useMutation({
    mutationFn: () => api.createSite(siteForm),
    onSuccess: () => {
      setSiteForm({ name: '', ssid: '', nas_identifier: '' });
      void client.invalidateQueries({ queryKey: ['sites'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const createNas = useMutation({
    mutationFn: () =>
      api.createNas({
        site_id: Number(nasForm.site_id),
        name: nasForm.name,
        nasname: nasForm.nasname,
        shared_secret: nasForm.shared_secret || null,
      }),
    onSuccess: () => {
      setNasForm({ ...nasForm, name: '', nasname: '', shared_secret: '' });
      void client.invalidateQueries({ queryKey: ['nas'] });
    },
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

  return (
    <div>
      <PageHeader title="Routers" subtitle="Onboard a MikroTik with a generated .rsc snippet and login.html." />
      <ErrorBanner message={error} />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 font-semibold">New site</h2>
          <form
            className="space-y-3"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              createSite.mutate();
            }}
          >
            <Field label="Name">
              <input className={inputClass} value={siteForm.name} onChange={(e) => setSiteForm({ ...siteForm, name: e.target.value })} required />
            </Field>
            <Field label="SSID">
              <input className={inputClass} value={siteForm.ssid} onChange={(e) => setSiteForm({ ...siteForm, ssid: e.target.value })} />
            </Field>
            <Field label="NAS-Identifier">
              <input className={inputClass} value={siteForm.nas_identifier} onChange={(e) => setSiteForm({ ...siteForm, nas_identifier: e.target.value })} required />
            </Field>
            <button type="submit" className={primaryBtn} disabled={createSite.isPending}>
              Save site
            </button>
          </form>
        </Card>
        <Card>
          <h2 className="mb-3 font-semibold">New router</h2>
          <form
            className="space-y-3"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              createNas.mutate();
            }}
          >
            <Field label="Site">
              <select className={inputClass} value={nasForm.site_id} onChange={(e) => setNasForm({ ...nasForm, site_id: e.target.value })} required>
                <option value="">Select…</option>
                {(sites.data?.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Name">
              <input className={inputClass} value={nasForm.name} onChange={(e) => setNasForm({ ...nasForm, name: e.target.value })} required />
            </Field>
            <Field label="Router IP">
              <input className={inputClass} value={nasForm.nasname} onChange={(e) => setNasForm({ ...nasForm, nasname: e.target.value })} required />
            </Field>
            <Field label="Shared secret (blank to generate)">
              <input className={inputClass} value={nasForm.shared_secret} onChange={(e) => setNasForm({ ...nasForm, shared_secret: e.target.value })} />
            </Field>
            <button type="submit" className={primaryBtn} disabled={createNas.isPending}>
              Save router
            </button>
          </form>
        </Card>
      </div>
      <div className="mt-4 space-y-3">
        {(routers.data?.data ?? []).map((router) => (
          <Card key={router.id}>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="font-semibold">{router.name}</p>
                <p className="text-sm text-ink-700">
                  {router.nasname} · {router.site?.name}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={router.status === 'active' ? 'green' : 'slate'}>{router.status}</Badge>
                <button type="button" className={secondaryBtn} onClick={() => void loadSnippet(router.id, router.name)}>
                  Snippet
                </button>
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!routers.data?.data.length && <Empty>{routers.isLoading ? 'Loading routers…' : 'No routers yet.'}</Empty>}
      {snippet && (
        <Card className="mt-4">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 className="font-semibold">Onboarding for {snippet.name}</h2>
            <div className="flex gap-2">
              <button type="button" className={primaryBtn} onClick={() => download(snippet.rsc, `${snippet.name}.rsc`)}>
                Download .rsc
              </button>
              <button type="button" className={secondaryBtn} onClick={() => download(snippet.login_html, 'login.html')}>
                login.html
              </button>
            </div>
          </div>
          <pre className="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{snippet.rsc}</pre>
        </Card>
      )}
    </div>
  );
}
