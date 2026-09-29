import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { api } from '../api';
import { Callout, Card, ErrorBanner, Field, PageHeader, inputClass, primaryBtn, secondaryBtn } from '../components/ui';
import { PasswordForm } from '../components/PasswordForm';
import { useAuth } from '../auth';

export function SettingsPage() {
  const { user } = useAuth();
  const client = useQueryClient();
  const settings = useQuery({ queryKey: ['settings'], queryFn: api.settings });
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ portal_name: '', support_phone: '', palmpesa_api_token: '' });
  const [twoFactorSvg, setTwoFactorSvg] = useState<string | null>(null);
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [disablePassword, setDisablePassword] = useState('');
  const loaded = settings.data?.data;
  const lipiaOn = Boolean(loaded?.palmpesa_configured || loaded?.accepts_online_payments);

  const save = useMutation({
    mutationFn: () =>
      api.updateSettings({
        portal_name: form.portal_name || loaded?.portal_name,
        support_phone: form.support_phone || loaded?.support_phone,
        palmpesa_api_token: form.palmpesa_api_token || undefined,
      }),
    onSuccess: () => {
      setForm({ ...form, palmpesa_api_token: '' });
      void client.invalidateQueries({ queryKey: ['settings'] });
      void client.invalidateQueries({ queryKey: ['dashboard'] });
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
    <div>
      <PageHeader title="Settings" subtitle="Jina la portal, simu ya Msaada, logo, Lipia (PalmPesa), na 2FA." />
      <ErrorBanner message={error} />
      {loaded && !lipiaOn && (
        <Callout tone="amber">
          Lipia haijawekwa — wateja wananunua kadi tu. Weka token ya PalmPesa kwenye kadi ya Lipia hapa chini.
        </Callout>
      )}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Portal</h2>
          {loaded?.logo_url && <img src={loaded.logo_url} alt="" className="mb-3 h-16 object-contain" />}
          <form
            className="space-y-3"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              setError(null);
              save.mutate();
            }}
          >
            <Field label="Portal name">
              <input
                className={inputClass}
                defaultValue={loaded?.portal_name ?? ''}
                onChange={(e) => setForm({ ...form, portal_name: e.target.value })}
                placeholder={user?.tenant?.name}
              />
            </Field>
            <Field label="Owner / Msaada phone">
              <input
                className={inputClass}
                defaultValue={loaded?.support_phone ?? ''}
                onChange={(e) => setForm({ ...form, support_phone: e.target.value })}
                placeholder="07XXXXXXXX"
              />
            </Field>
            <Field label="Logo">
              <input
                className={inputClass}
                type="file"
                accept="image/*"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) void onLogo(file);
                }}
              />
            </Field>
            <button type="submit" className={primaryBtn} disabled={save.isPending}>
              Save
            </button>
          </form>
        </Card>
        <Card>
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Lipia (PalmPesa)</h2>
          <p className="mb-3 text-sm text-ink-700">
            Token hii inafungua malipo kwenye portal. Bila hiyo, wateja hununua kadi kwa wakala tu. Si token ya bili ya Kasi.
          </p>
          <p className="mb-3 text-sm font-medium text-ink-800">{lipiaOn ? 'Imewekwa' : 'Haijawekwa'}</p>
          <form
            className="space-y-3"
            onSubmit={(event: FormEvent) => {
              event.preventDefault();
              setError(null);
              save.mutate();
            }}
          >
            <Field label="PalmPesa token (wazi = usibadilishe)">
              <input
                className={inputClass}
                type="password"
                value={form.palmpesa_api_token}
                onChange={(e) => setForm({ ...form, palmpesa_api_token: e.target.value })}
                autoComplete="off"
              />
            </Field>
            <button type="submit" className={primaryBtn} disabled={save.isPending}>
              Save Lipia
            </button>
          </form>
        </Card>
        <Card>
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">2FA</h2>
          {user?.two_factor_enabled && !twoFactorSvg ? (
            <form
              className="space-y-3"
              onSubmit={(event: FormEvent) => {
                event.preventDefault();
                setError(null);
                void api
                  .disableTwoFactor(disablePassword)
                  .then(() => {
                    setDisablePassword('');
                    window.location.reload();
                  })
                  .catch((err: Error) => setError(err.message));
              }}
            >
              <p className="text-sm text-ink-800">Authenticator is on.</p>
              <Field label="Password to turn off">
                <input className={inputClass} type="password" value={disablePassword} onChange={(e) => setDisablePassword(e.target.value)} />
              </Field>
              <button type="submit" className={secondaryBtn}>
                Disable 2FA
              </button>
            </form>
          ) : (
            <div className="space-y-3">
              {!twoFactorSvg && (
                <button
                  type="button"
                  className={primaryBtn}
                  onClick={() => {
                    setError(null);
                    void api
                      .startTwoFactor()
                      .then((data) => setTwoFactorSvg(data.qr_svg))
                      .catch((err: Error) => setError(err.message));
                  }}
                >
                  Enable 2FA
                </button>
              )}
              {twoFactorSvg && (
                <form
                  className="space-y-3"
                  onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    void api
                      .confirmTwoFactor(twoFactorCode)
                      .then(() => window.location.reload())
                      .catch((err: Error) => setError(err.message));
                  }}
                >
                  <div className="w-48" dangerouslySetInnerHTML={{ __html: twoFactorSvg }} />
                  <Field label="Code from the app">
                    <input className={inputClass} value={twoFactorCode} onChange={(e) => setTwoFactorCode(e.target.value)} />
                  </Field>
                  <button type="submit" className={primaryBtn}>
                    Confirm
                  </button>
                </form>
              )}
            </div>
          )}
        </Card>
        <Card>
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Nenosiri</h2>
          <PasswordForm />
        </Card>
      </div>
    </div>
  );
}
