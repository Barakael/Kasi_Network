import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState, type FormEvent } from 'react';
import { api } from '../api';
import { PasswordForm } from '../components/PasswordForm';
import { Card, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { useAuth } from '../auth';
import { usePrefs } from '../preferences';

export function SettingsPage() {
  const { user } = useAuth();
  const { sw } = usePrefs();
  const client = useQueryClient();
  const settings = useQuery({ queryKey: ['settings'], queryFn: api.settings });
  const campaigns = useQuery({ queryKey: ['campaigns'], queryFn: api.campaigns });
  const [error, setError] = useState<string | null>(null);
  const [portal, setPortal] = useState({ portal_name: '', support_phone: '' });
  const [pay, setPay] = useState({ palmpesa_user_id: '', palmpesa_vendor: '', palmpesa_api_token: '' });
  const [notice, setNotice] = useState({ title: '', body: '', audience: 'all' });
  const [twoFactorSvg, setTwoFactorSvg] = useState<string | null>(null);
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const loaded = settings.data?.data;

  useEffect(() => {
    if (!loaded) {
      return;
    }
    setPortal({ portal_name: loaded.portal_name ?? '', support_phone: loaded.support_phone ?? '' });
    setPay((current) => ({
      ...current,
      palmpesa_user_id: loaded.palmpesa_user_id ?? '',
      palmpesa_vendor: loaded.palmpesa_vendor ?? '',
    }));
  }, [loaded]);

  const save = useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.updateSettings(payload),
    onSuccess: () => {
      setPay((current) => ({ ...current, palmpesa_api_token: '' }));
      void client.invalidateQueries({ queryKey: ['settings'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const createNotice = useMutation({
    mutationFn: () => api.createCampaign(notice),
    onSuccess: () => {
      setNotice({ title: '', body: '', audience: 'all' });
      void client.invalidateQueries({ queryKey: ['campaigns'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  async function onLogo(file: File) {
    setError(null);
    try {
      await api.uploadLogo(file);
      void client.invalidateQueries({ queryKey: ['settings'] });
    } catch (err) {
      setError((err as Error).message);
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={sw('Mipangilio', 'Settings')} />
      <ErrorBanner message={error} />
      <Card>
        <h2 className="mb-3 text-sm font-semibold text-ink-700">{sw('Portal', 'Portal')}</h2>
        {loaded?.logo_url && <img src={loaded.logo_url} alt="" className="mb-3 h-16 object-contain" />}
        <form
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            save.mutate(portal);
          }}
        >
          <Field label={sw('Jina', 'Name')}>
            <input className={inputClass} value={portal.portal_name} onChange={(e) => setPortal({ ...portal, portal_name: e.target.value })} placeholder={user?.tenant?.name} />
          </Field>
          <Field label={sw('Simu ya msaada', 'Support phone')}>
            <input className={inputClass} value={portal.support_phone} onChange={(e) => setPortal({ ...portal, support_phone: e.target.value })} placeholder="07XXXXXXXX" />
          </Field>
          <Field label={sw('Nembo', 'Logo')}>
            <input className={inputClass} type="file" accept="image/*" onChange={(e) => { const file = e.target.files?.[0]; if (file) void onLogo(file); }} />
          </Field>
          <div className="flex items-end">
            <button type="submit" className={primaryBtn} disabled={save.isPending}>{sw('Hifadhi', 'Save')}</button>
          </div>
        </form>
      </Card>
      <Card>
        <h2 className="mb-1 text-sm font-semibold text-ink-700">{sw('Malipo ya wateja', 'Customer payments')}</h2>
        <p className="mb-3 text-sm text-ink-700">{sw('Acha wazi usipotaka kubadilisha token.', 'Leave blank to keep the current token.')}</p>
        <form
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            save.mutate({
              palmpesa_user_id: pay.palmpesa_user_id,
              palmpesa_vendor: pay.palmpesa_vendor,
              ...(pay.palmpesa_api_token ? { palmpesa_api_token: pay.palmpesa_api_token } : {}),
            });
          }}
        >
          <Field label={sw('User ID', 'User ID')}>
            <input className={inputClass} value={pay.palmpesa_user_id} onChange={(e) => setPay({ ...pay, palmpesa_user_id: e.target.value })} />
          </Field>
          <Field label={sw('Vendor', 'Vendor')}>
            <input className={inputClass} value={pay.palmpesa_vendor} onChange={(e) => setPay({ ...pay, palmpesa_vendor: e.target.value })} />
          </Field>
          <Field label={sw('API token', 'API token')}>
            <input className={inputClass} type="password" autoComplete="off" value={pay.palmpesa_api_token} onChange={(e) => setPay({ ...pay, palmpesa_api_token: e.target.value })} />
          </Field>
          <div className="flex items-end">
            <button type="submit" className={primaryBtn} disabled={save.isPending}>{sw('Hifadhi', 'Save')}</button>
          </div>
        </form>
      </Card>
      <Card>
        <h2 className="mb-3 text-sm font-semibold text-ink-700">{sw('Tangazo kwenye portal', 'Portal notice')}</h2>
        <ul className="mb-3 space-y-2">
          {(campaigns.data?.data ?? []).map((item) => (
            <li key={item.id} className="text-sm text-ink-800">
              <strong>{item.title}</strong> — {item.body}
            </li>
          ))}
        </ul>
        <form
          className="grid gap-3"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            createNotice.mutate();
          }}
        >
          <Field label={sw('Jina', 'Name')}>
            <input className={inputClass} value={notice.title} onChange={(e) => setNotice({ ...notice, title: e.target.value })} required />
          </Field>
          <Field label={sw('Maelezo', 'Details')}>
            <textarea className={inputClass} value={notice.body} onChange={(e) => setNotice({ ...notice, body: e.target.value })} required />
          </Field>
          <button type="submit" className={secondaryBtn} disabled={createNotice.isPending}>{sw('Hifadhi', 'Save')}</button>
        </form>
      </Card>
      <Card>
        <h2 className="mb-3 text-sm font-semibold text-ink-700">{sw('Nenosiri', 'Password')}</h2>
        <PasswordForm />
        <div className="mt-4">
          {user?.two_factor_enabled && !twoFactorSvg ? (
            <p className="text-sm text-ink-800">2FA</p>
          ) : (
            <div className="space-y-3">
              {!twoFactorSvg && (
                <button
                  type="button"
                  className={secondaryBtn}
                  onClick={() => {
                    setError(null);
                    void api.startTwoFactor().then((data) => setTwoFactorSvg(data.qr_svg)).catch((err: Error) => setError(err.message));
                  }}
                >
                  2FA
                </button>
              )}
              {twoFactorSvg && (
                <form
                  className="space-y-3"
                  onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    void api.confirmTwoFactor(twoFactorCode).then(() => window.location.reload()).catch((err: Error) => setError(err.message));
                  }}
                >
                  <div className="w-48" dangerouslySetInnerHTML={{ __html: twoFactorSvg }} />
                  <input className={inputClass} value={twoFactorCode} onChange={(e) => setTwoFactorCode(e.target.value)} />
                  <button type="submit" className={primaryBtn}>{sw('Hifadhi', 'Save')}</button>
                </form>
              )}
            </div>
          )}
        </div>
      </Card>
    </div>
  );
}
