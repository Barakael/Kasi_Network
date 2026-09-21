import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import {
  formatPrice,
  portalApi,
  type Bootstrap,
  type NearbyDevice,
  type Plan,
} from './api';
import { submitHotspotLogin } from './chap';
import { QrScanner } from './QrScanner';

type ScanTarget = 'main' | 'device';

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
  const [code, setCode] = useState(pathCode() ?? '');
  const [busy, setBusy] = useState(false);
  const [phone, setPhone] = useState('');
  const [plan, setPlan] = useState<Plan | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [hosts, setHosts] = useState<NearbyDevice[]>([]);
  const [deviceMac, setDeviceMac] = useState('');
  const [deviceCode, setDeviceCode] = useState('');
  const [autoRedeemed, setAutoRedeemed] = useState(false);
  const [showPay, setShowPay] = useState(false);
  const [scanTarget, setScanTarget] = useState<ScanTarget | null>(null);

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
      })
      .catch((err: Error) => setError(err.message));
  }, [params, site]);

  useEffect(() => {
    if (!boot?.capabilities.device_discovery) {
      return;
    }
    void portalApi.nearby(boot.token).then((result) => setHosts(result.data));
  }, [boot]);

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

  async function redeemCode(raw: string) {
    if (!boot) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await portalApi.redeem(boot.token, raw);
      setCode(result.display_code || result.code);
      loginWith(result.code);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    const fromQr = pathCode();
    if (!boot || !fromQr || autoRedeemed || busy) {
      return;
    }
    setAutoRedeemed(true);
    void redeemCode(fromQr);
  }, [boot, autoRedeemed, busy]);

  async function onRedeem(event: FormEvent) {
    event.preventDefault();
    await redeemCode(code);
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

  async function bindDevice(event: FormEvent) {
    event.preventDefault();
    if (!boot || deviceMac.length < 11 || deviceCode.length < 6) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await portalApi.bind(boot.token, deviceCode, deviceMac);
      setStatus(`Bound ${deviceMac}. That device will come online on its own.`);
      setDeviceCode('');
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (!boot && !error && !missingSite) {
    return (
      <Shell>
        <p className="m-auto text-center text-sm text-leaf-700">Connecting…</p>
      </Shell>
    );
  }

  if (!boot) {
    return (
      <Shell>
        <div className="portal-content m-auto justify-center text-center">
          <p className="text-xs font-semibold tracking-[0.16em] text-leaf-700 uppercase">Kasi-Net</p>
          <h1 className="portal-brand mt-1 text-3xl text-leaf-900">Get online</h1>
          <p className="mt-2 text-sm text-leaf-800">{error || missingSite}</p>
          {code && <p className="mt-2 font-mono text-sm tracking-wide text-leaf-700">{code}</p>}
        </div>
      </Shell>
    );
  }

  const nearby = hosts.slice(0, 2);

  return (
    <Shell>
      <div className="portal-content">
        <header className="shrink-0 text-center">
          <p className="text-[0.7rem] font-semibold tracking-[0.18em] text-leaf-700 uppercase">Kasi-Net</p>
          <h1 className="portal-brand mt-0.5 text-[1.65rem] leading-tight text-leaf-900">
            {boot.branding.operator || 'Stay connected'}
          </h1>
          <p className="mt-1 text-[0.8rem] leading-snug text-leaf-800">
            Wi‑Fi yenye kasi kwa matumizi ya kila siku.
          </p>
          {(boot.site.ssid || boot.branding.support_phone) && (
            <p className="mt-1 text-[0.7rem] text-leaf-700">
              {boot.site.ssid}
              {boot.site.ssid && boot.branding.support_phone ? ' · ' : ''}
              {boot.branding.support_phone ? `Help ${boot.branding.support_phone}` : ''}
            </p>
          )}
        </header>

        {(error || status) && (
          <p
            className={`shrink-0 rounded-xl px-3 py-1.5 text-center text-xs ${
              error ? 'bg-red-50/95 text-red-800' : 'bg-leaf-100/95 text-leaf-800'
            }`}
            role={error ? 'alert' : undefined}
          >
            {error || status}
          </p>
        )}

        <form onSubmit={onRedeem} className="shrink-0 space-y-2">
          <div className="flex items-end gap-2">
            <label className="block min-w-0 flex-1 text-left text-xs font-medium text-leaf-800">
              Voucher
              <input
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                className="portal-field mt-1 font-mono text-base tracking-[0.12em] placeholder:tracking-normal placeholder:text-leaf-300"
                placeholder="XXXXX-XXXXX"
              />
            </label>
            <button
              type="button"
              onClick={() => setScanTarget('main')}
              className="portal-btn shrink-0 border border-leaf-300/80 bg-white/85 px-3 text-xs text-leaf-700"
            >
              Scan
            </button>
          </div>
          <button
            type="submit"
            disabled={busy || code.length < 6}
            className="portal-cta portal-btn w-full bg-leaf-600 text-sm text-white enabled:hover:bg-leaf-700 disabled:opacity-45"
          >
            {busy ? 'Connecting…' : 'Pokea Wi‑Fi'}
          </button>
        </form>

        {boot.capabilities.online_payments && (
          <div className="shrink-0 text-center">
            <button
              type="button"
              onClick={() => setShowPay((open) => !open)}
              className="text-[0.7rem] font-medium text-leaf-700 underline decoration-leaf-300 underline-offset-2"
            >
              {showPay ? 'Ficha malipo' : 'Lipa kwa mobile money'}
            </button>
            {showPay && (
              <form onSubmit={onBuy} className="mt-2 max-h-[28dvh] space-y-2 overflow-hidden text-left">
                <ul className="grid max-h-[14dvh] gap-1.5 overflow-hidden">
                  {boot.plans.slice(0, 3).map((item) => (
                    <li key={item.id}>
                      <button
                        type="button"
                        onClick={() => setPlan(item)}
                        className={`min-h-11 w-full rounded-xl border px-3 py-2 text-left text-xs ${
                          plan?.id === item.id
                            ? 'border-leaf-600 bg-leaf-100'
                            : 'border-leaf-200/80 bg-white/70'
                        }`}
                      >
                        <span className="font-semibold text-leaf-900">{item.name}</span>
                        <span className="ml-2 text-leaf-700">
                          {formatPrice(item.price_minor, boot.branding.currency)}
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
                <input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  inputMode="tel"
                  className="portal-field text-sm"
                  placeholder="07XXXXXXXX"
                  aria-label="Mobile money number"
                />
                <button
                  type="submit"
                  disabled={busy || !plan || phone.length < 9}
                  className="portal-btn w-full bg-leaf-700 text-sm text-white disabled:opacity-45"
                >
                  {busy ? 'Waiting…' : 'Pay and connect'}
                </button>
              </form>
            )}
          </div>
        )}

        <section className="mt-auto shrink-0 border-t border-leaf-200/70 pt-2.5">
          <div className="mb-1.5 flex items-baseline justify-between gap-2">
            <h2 className="portal-brand text-base text-leaf-900">Ongeza kifaa</h2>
            <p className="text-[0.65rem] text-leaf-600">Voucher tofauti kwa kila kifaa</p>
          </div>

          {nearby.length > 0 && (
            <div className="mb-1.5 flex gap-1.5">
              {nearby.map((host) => (
                <button
                  key={host.mac}
                  type="button"
                  onClick={() => setDeviceMac(host.mac)}
                  className={`min-h-9 flex-1 truncate rounded-xl border px-2 text-[0.65rem] ${
                    deviceMac === host.mac
                      ? 'border-leaf-600 bg-leaf-100 text-leaf-900'
                      : 'border-leaf-200/80 bg-white/75 text-leaf-700'
                  }`}
                >
                  {host.vendor || host.mac}
                </button>
              ))}
            </div>
          )}

          <form onSubmit={bindDevice} className="space-y-1.5">
            <input
              value={deviceMac}
              onChange={(e) => setDeviceMac(e.target.value)}
              placeholder="MAC · AA:BB:CC:DD:EE:FF"
              aria-label="Device MAC address"
              className="portal-field font-mono text-xs"
            />
            <div className="flex gap-2">
              <input
                value={deviceCode}
                onChange={(e) => setDeviceCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                placeholder="Voucher ya kifaa"
                aria-label="Device voucher"
                className="portal-field min-w-0 flex-1 font-mono text-xs tracking-[0.1em] placeholder:tracking-normal"
              />
              <button
                type="button"
                onClick={() => setScanTarget('device')}
                className="portal-btn shrink-0 border border-leaf-300/80 bg-white/85 px-3 text-xs text-leaf-700"
              >
                Scan
              </button>
            </div>
            <button
              type="submit"
              disabled={busy || deviceMac.length < 11 || deviceCode.length < 6}
              className="portal-btn w-full bg-leaf-600 text-sm text-white disabled:opacity-45"
            >
              {busy ? 'Binding…' : 'Unganisha kifaa'}
            </button>
          </form>
        </section>

        <footer className="shrink-0 pt-1 text-center text-[0.6rem] tracking-wide text-leaf-600/75">
          Powered by Kasi-Net
        </footer>
      </div>

      <QrScanner
        open={scanTarget !== null}
        onClose={() => setScanTarget(null)}
        onCode={(scanned) => {
          if (scanTarget === 'device') {
            setDeviceCode(scanned);
            setStatus('Voucher ya kifaa imejazwa kutoka QR.');
            return;
          }
          setCode(scanned);
          void redeemCode(scanned);
        }}
      />
    </Shell>
  );
}

function Shell({ children }: { children: ReactNode }) {
  return (
    <main className="portal-shell">
      <div className="portal-bg" aria-hidden>
        <picture>
          <source srcSet="/people-enjoying.webp" type="image/webp" />
          <img src="/people-enjoying.jpg" alt="" decoding="async" />
        </picture>
      </div>
      <div className="portal-wash" aria-hidden />
      {children}
    </main>
  );
}
