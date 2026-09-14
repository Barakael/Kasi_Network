import { useEffect, useMemo, useState, type FormEvent } from 'react';
import {
  formatBytes,
  formatPrice,
  portalApi,
  type Bootstrap,
  type NearbyDevice,
  type Plan,
} from './api';
import { submitHotspotLogin } from './chap';

type Tab = 'voucher' | 'buy' | 'device';

function query(): URLSearchParams {
  return new URLSearchParams(window.location.search);
}

function pathCode(): string | null {
  const match = window.location.pathname.match(/^\/r\/([A-Z0-9-]+)/i);
  return match?.[1] ?? null;
}

export function App() {
  const params = useMemo(() => query(), []);
  const site = params.get('site');
  const [boot, setBoot] = useState<Bootstrap | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('voucher');
  const [code, setCode] = useState(pathCode() ?? '');
  const [busy, setBusy] = useState(false);
  const [phone, setPhone] = useState('');
  const [plan, setPlan] = useState<Plan | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [redeemed, setRedeemed] = useState<string | null>(null);
  const [hosts, setHosts] = useState<NearbyDevice[]>([]);
  const [manualMac, setManualMac] = useState('');

  const missingSite = site
    ? null
    : pathCode()
      ? 'Connect to the Wi-Fi hotspot first, then scan this voucher again from the login page.'
      : 'Open this page from the Wi-Fi login screen, or scan a voucher QR.';

  useEffect(() => {
    if (!site) {
      return;
    }

    portalApi
      .bootstrap({
        site,
        mac: params.get('mac') ?? '',
        ip: params.get('ip') ?? '',
        link_login: params.get('link_login') ?? '',
      })
      .then((data) => {
        setBoot(data);
        document.title = data.branding.operator;
        if (data.branding.primary_color) {
          document.documentElement.style.setProperty('--color-brand-600', data.branding.primary_color);
        }
      })
      .catch((err: Error) => setError(err.message));
  }, [params, site]);

  function loginWith(voucherCode: string) {
    const link = params.get('link_login');
    if (!link) {
      setStatus('Voucher accepted. Connect through the hotspot login page to go online.');
      return;
    }
    submitHotspotLogin(
      link,
      voucherCode,
      voucherCode,
      params.get('chap_id'),
      params.get('chap_challenge'),
      params.get('link_orig'),
    );
  }

  async function onRedeem(event: FormEvent) {
    event.preventDefault();
    if (!boot) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await portalApi.redeem(boot.token, code);
      setRedeemed(result.code);
      loginWith(result.code);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onBuy(event: FormEvent) {
    event.preventDefault();
    if (!boot || !plan) {
      return;
    }
    setBusy(true);
    setError(null);
    setStatus('Check your phone for the payment prompt…');
    try {
      const created = await portalApi.createOrder(boot.token, plan.id, phone);
      for (let i = 0; i < 45; i++) {
        await new Promise((r) => setTimeout(r, 2000));
        const current = await portalApi.order(boot.token, created.data.uuid);
        if (current.data.code) {
          setRedeemed(current.data.code);
          loginWith(current.data.code);
          return;
        }
        if (['failed', 'expired', 'voided'].includes(current.data.status)) {
          throw new Error(current.data.status_label || 'Payment did not complete.');
        }
      }
      throw new Error('Still waiting for payment. You can close this and try again.');
    } catch (err) {
      setError((err as Error).message);
      setStatus(null);
    } finally {
      setBusy(false);
    }
  }

  async function loadHosts() {
    if (!boot) {
      return;
    }
    const result = await portalApi.nearby(boot.token);
    setHosts(result.data);
  }

  async function bindMac(mac: string) {
    if (!boot || !redeemed) {
      return;
    }
    setBusy(true);
    try {
      await portalApi.bind(boot.token, redeemed, mac);
      setStatus(`Bound ${mac}. That device will come online on its own.`);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (!boot && !error && !missingSite) {
    return (
      <main className="mx-auto flex min-h-dvh max-w-lg items-center justify-center px-4">
        <p className="text-ink-800">Connecting to this hotspot…</p>
      </main>
    );
  }

  if (!boot) {
    return (
      <main className="mx-auto flex min-h-dvh max-w-lg flex-col items-center justify-center gap-3 px-4 text-center">
        <h1 className="text-xl font-bold text-ink-900">Kasi Network</h1>
        <p className="text-red-700">{error || missingSite}</p>
        {code && (
          <p className="font-mono text-lg tracking-wide text-ink-800">
            Voucher: {code}
          </p>
        )}
      </main>
    );
  }

  return (
    <main className="mx-auto min-h-dvh max-w-3xl px-4 py-6 pb-16">
      <header className="mb-6">
        <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">
          {boot.site.ssid || boot.site.name}
        </p>
        <h1 className="mt-1 text-2xl font-bold text-ink-900">{boot.branding.operator}</h1>
        {boot.branding.support_phone && (
          <p className="mt-1 text-sm text-ink-800">Help: {boot.branding.support_phone}</p>
        )}
      </header>

      {error && (
        <p className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
          {error}
        </p>
      )}
      {status && <p className="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-sm text-ink-800">{status}</p>}

      <nav className="mb-4 grid grid-cols-3 gap-2">
        {(['voucher', 'buy', 'device'] as Tab[]).map((id) => (
          <button
            key={id}
            type="button"
            onClick={() => {
              setTab(id);
              if (id === 'device') {
                void loadHosts();
              }
            }}
            className={`min-h-12 rounded-lg px-2 text-sm font-semibold ${
              tab === id ? 'bg-brand-600 text-white' : 'bg-slate-100 text-ink-800'
            }`}
          >
            {id === 'voucher' ? 'Voucher' : id === 'buy' ? 'Pay' : 'Add device'}
          </button>
        ))}
      </nav>

      {tab === 'voucher' && (
        <form onSubmit={onRedeem} className="space-y-3">
          <label className="block text-sm font-medium text-ink-800">
            Voucher code
            <input
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              autoComplete="off"
              autoCapitalize="characters"
              inputMode="text"
              className="mt-1 min-h-12 w-full rounded-lg border border-slate-300 px-3 font-mono text-lg tracking-wide"
              placeholder="KAS-XXXX-XXXX-XX"
            />
          </label>
          <button
            type="submit"
            disabled={busy || code.length < 6}
            className="min-h-12 w-full rounded-lg bg-brand-600 font-semibold text-white disabled:opacity-50"
          >
            {busy ? 'Connecting…' : 'Go online'}
          </button>
        </form>
      )}

      {tab === 'buy' && (
        <div>
          {!boot.capabilities.online_payments ? (
            <p className="text-ink-800">Buy a printed voucher from the attendant.</p>
          ) : (
            <form onSubmit={onBuy} className="space-y-4">
              <ul className="grid gap-3 sm:grid-cols-2">
                {boot.plans.map((item) => (
                  <li key={item.id}>
                    <button
                      type="button"
                      onClick={() => setPlan(item)}
                      className={`min-h-24 w-full rounded-xl border p-4 text-left ${
                        plan?.id === item.id ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white'
                      }`}
                    >
                      <p className="font-semibold text-ink-900">{item.name}</p>
                      <p className="text-sm text-ink-800">{item.billing_period_label}</p>
                      <p className="mt-2 text-lg font-bold text-brand-700">
                        {formatPrice(item.price_minor, boot.branding.currency)}
                      </p>
                      <p className="text-xs text-ink-800">
                        {[formatBytes(item.data_cap_bytes), item.rate_limit_down_kbps ? `${item.rate_limit_down_kbps} kbps` : null]
                          .filter(Boolean)
                          .join(' · ')}
                      </p>
                    </button>
                  </li>
                ))}
              </ul>
              <label className="block text-sm font-medium text-ink-800">
                Mobile money number
                <input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  inputMode="tel"
                  className="mt-1 min-h-12 w-full rounded-lg border border-slate-300 px-3"
                  placeholder="07XXXXXXXX"
                />
              </label>
              <button
                type="submit"
                disabled={busy || !plan || phone.length < 9}
                className="min-h-12 w-full rounded-lg bg-brand-600 font-semibold text-white disabled:opacity-50"
              >
                {busy ? 'Waiting for payment…' : 'Pay and connect'}
              </button>
            </form>
          )}
        </div>
      )}

      {tab === 'device' && (
        <div className="space-y-3">
          <p className="text-sm text-ink-800">
            Use this phone to put a TV or console online. Redeem a voucher first, then pick the device.
          </p>
          {!redeemed && (
            <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm">Redeem a voucher on the first tab before binding.</p>
          )}
          {hosts.map((host) => (
            <button
              key={host.mac}
              type="button"
              disabled={!redeemed || busy}
              onClick={() => void bindMac(host.mac)}
              className="flex min-h-14 w-full items-center justify-between rounded-lg border border-slate-200 px-3 text-left"
            >
              <span>
                <span className="font-medium">{host.vendor || 'Unknown device'}</span>
                <span className="ml-2 font-mono text-xs text-ink-800">{host.mac}</span>
              </span>
              <span className="text-sm text-brand-700">Bind</span>
            </button>
          ))}
          <form
            className="flex gap-2"
            onSubmit={(e) => {
              e.preventDefault();
              void bindMac(manualMac);
            }}
          >
            <input
              value={manualMac}
              onChange={(e) => setManualMac(e.target.value)}
              placeholder="AA:BB:CC:DD:EE:FF"
              className="min-h-12 flex-1 rounded-lg border border-slate-300 px-3 font-mono"
            />
            <button type="submit" disabled={!redeemed || busy} className="min-h-12 rounded-lg bg-brand-600 px-4 font-semibold text-white">
              Add
            </button>
          </form>
        </div>
      )}
    </main>
  );
}
