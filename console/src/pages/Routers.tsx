import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Badge, Callout, Card, Empty, ErrorBanner, Field, Guide, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { usePrefs } from '../preferences';

export function RoutersPage() {
  const { sw } = usePrefs();
  const client = useQueryClient();
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const routers = useQuery({ queryKey: ['nas'], queryFn: api.nasDevices });
  const [error, setError] = useState<string | null>(null);
  const [snippet, setSnippet] = useState<{ name: string; rsc: string; login_html: string } | null>(null);
  const [nasForm, setNasForm] = useState({ site_id: '', name: '', nasname: '', api_host: '', shared_secret: '' });

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
      <PageHeader title={sw('Vifaa', 'Devices')} subtitle={sw('Sajili MikroTik. Jina, eneo, na anwani. Sanidi inafungua siri na snippet.', 'Register a MikroTik. Name, site, and address. Set up opens the secret and snippet.')} />
      <ErrorBanner message={error} />
      {sites.isSuccess && siteCount === 0 && (
        <Guide title={sw('Weka eneo kwanza', 'Add a site first')} body={sw('Kifaa kinahitaji eneo. Tengeneza eneo, kisha rudi hapa kuongeza MikroTik.', 'A device needs a site. Add a site, then come back to add the MikroTik.')} to="/sites" cta={sw('Ongeza eneo', 'Add site')} />
      )}
      {quietCount > 0 && (
        <Callout tone="amber">
          {quietCount} {sw(quietCount === 1 ? 'router iko kimya' : 'routers ziko kimya', quietCount === 1 ? 'router is quiet' : 'routers are quiet')} — {sw('RADIUS haijaona paketi. Angalia IP, siri, na snippet.', 'RADIUS has not seen a packet. Check the IP, secret, and snippet.')}
        </Callout>
      )}
      {siteCount > 0 && (
        <Card className="mb-4">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">{sw('MikroTik mpya', 'New MikroTik')}</h2>
          <form
            className="grid gap-3 sm:grid-cols-2"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              createNas.mutate();
            }}
          >
            <Field label={sw('Eneo', 'Site')}>
              <select className={inputClass} value={nasForm.site_id} onChange={(e) => setNasForm({ ...nasForm, site_id: e.target.value })} required>
                <option value="">{sw('Chagua eneo…', 'Choose a site…')}</option>
                {(sites.data?.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label={sw('Jina', 'Name')}>
              <input className={inputClass} value={nasForm.name} onChange={(e) => setNasForm({ ...nasForm, name: e.target.value })} required />
            </Field>
            <Field label={sw('Anwani ya RADIUS', 'RADIUS address')}>
              <input
                className={inputClass}
                value={nasForm.nasname}
                onChange={(e) => setNasForm({ ...nasForm, nasname: e.target.value })}
                placeholder={sw('IP ya umma ambayo RADIUS inaona', 'Public IP that RADIUS sees')}
                required
              />
            </Field>
            <Field label={sw('Hotspot LAN IP (si lazima)', 'Hotspot LAN IP (optional)')}>
              <input
                className={inputClass}
                value={nasForm.api_host}
                onChange={(e) => setNasForm({ ...nasForm, api_host: e.target.value })}
                placeholder="192.168.10.212"
              />
            </Field>
            <Field label={sw('Siri (wazi = tengeneza)', 'Secret (blank = generate)')}>
              <input className={inputClass} value={nasForm.shared_secret} onChange={(e) => setNasForm({ ...nasForm, shared_secret: e.target.value })} />
            </Field>
            <div className="flex items-end">
              <button type="submit" className={primaryBtn} disabled={createNas.isPending}>
                {sw('Hifadhi', 'Save')}
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
                  {router.router_quiet ? sw(' · router kimya', ' · router quiet') : ''}
                </p>
                <p className="text-sm text-ink-700">{router.site?.name}</p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={router.router_quiet ? 'amber' : 'green'}>
                  {router.router_quiet ? sw('Kimya', 'Quiet') : sw('Inawasiliana', 'Speaking')}
                </Badge>
                <button type="button" className={secondaryBtn} onClick={() => void loadSnippet(router.id, router.name)}>
                  {sw('Sanidi', 'Set up')}
                </button>
              </div>
            </div>
          </Card>
        ))}
      </div>
      {!routers.data?.data.length && siteCount > 0 && (
        <Empty>{routers.isLoading ? sw('Inapakia vifaa…', 'Loading devices…') : sw('Hakuna MikroTik bado — jaza fomu hapo juu.', 'No MikroTik yet — fill in the form above.')}</Empty>
      )}
      {snippet && (
        <Card className="mt-4">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 className="font-semibold">{sw('Usanidi', 'Setup')}: {snippet.name}</h2>
            <div className="flex gap-2">
              <button type="button" className={primaryBtn} onClick={() => download(snippet.rsc, `${snippet.name}.rsc`)}>
                {sw('Pakua .rsc', 'Download .rsc')}
              </button>
              <button type="button" className={secondaryBtn} onClick={() => download(snippet.login_html, 'login.html')}>
                login.html
              </button>
            </div>
          </div>
          <p className="mb-2 text-sm text-ink-700">
            {sw('Weka faili kwenye MikroTik, kisha rudi', 'Put the file on the MikroTik, then return to')}{' '}
            <Link to="/customers" className="font-semibold text-brand-700 hover:underline">
              {sw('Wateja', 'Customers')}
            </Link>{' '}
            {sw('kuona simu.', 'to see phone numbers.')}
          </p>
          <pre className="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{snippet.rsc}</pre>
        </Card>
      )}
    </div>
  );
}
