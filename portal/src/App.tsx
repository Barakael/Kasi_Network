import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import {
  formatBytes,
  formatDuration,
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
        if (data.plans[0]) {
          setPlan(data.plans[0]);
        }
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
    try {
      if (boot.capabilities.online_payments) {
        setStatus('Check your phone for the payment prompt…');
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
      }

      if (boot.capabilities.demo_checkout) {
        setStatus('Inatengeneza voucher…');
        const issued = await portalApi.checkout(boot.token, plan.id);
        setCode(issued.display_code || issued.code);
        setStatus(`${issued.plan}: ${issued.display_code || issued.code}`);
        await redeemCode(issued.code);
        return;
      }

      throw new Error('Lipa kwa mhudumu, kisha weka voucher hapa chini.');
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
      <Shell
        hero={
          <>
            <p className="text-xs font-semibold tracking-[0.2em] uppercase">Kasi-Net</p>
            <h1 className="portal-brand mt-2 text-3xl leading-none">Inaunganisha…</h1>
          </>
        }
      >
        <p className="py-6 text-center text-sm text-leaf-700">Tafadhali subiri.</p>
      </Shell>
    );
  }

  if (!boot) {
    return (
      <Shell
        hero={
          <>
            <p className="text-xs font-semibold tracking-[0.2em] uppercase">Kasi-Net</p>
            <h1 className="portal-brand mt-2 text-3xl leading-none">Get online</h1>
          </>
        }
      >
        <p className="text-center text-sm leading-relaxed text-leaf-800">{error || missingSite}</p>
        {code && <p className="mt-3 text-center font-mono text-base tracking-wide text-leaf-700">{code}</p>}
      </Shell>
    );
  }

  const nearby = hosts.slice(0, 2);

  return (
    <>
    <Shell
      hero={
        <>
          <p className="text-xs font-semibold tracking-[0.22em] uppercase">Kasi-Net</p>
          <h1 className="portal-brand mt-1.5 text-[2.05rem] leading-none sm:text-4xl">
            {boot.branding.operator || 'Stay connected'}
          </h1>
          <p className="mx-auto mt-2 max-w-xs text-[0.95rem] leading-snug">
            Wi‑Fi yenye kasi kwa matumizi ya kila siku.
          </p>
          {(boot.site.ssid || boot.branding.support_phone) && (
            <p className="mt-2 text-sm text-white/90">
              {boot.site.ssid}
              {boot.site.ssid && boot.branding.support_phone ? ' · ' : ''}
              {boot.branding.support_phone ? `Help ${boot.branding.support_phone}` : ''}
            </p>
          )}
        </>
      }
    >
      <div className="portal-sheet-head">
        <p className="text-xs font-semibold tracking-[0.18em] uppercase text-leaf-600">Karibu</p>
        <p className="portal-brand mt-1 text-[1.65rem] leading-none text-leaf-900">
          {boot.branding.operator || 'Kasi-Net'}
        </p>
      </div>

      {(error || status) && (
        <p
          className={`rounded-xl px-3 py-2 text-center text-sm ${
            error ? 'bg-red-50 text-red-800' : 'bg-leaf-100 text-leaf-800'
          }`}
          role={error ? 'alert' : undefined}
        >
          {error || status}
        </p>
      )}

      <section className="portal-block">
        <div>
          <h2 className="portal-brand text-lg text-leaf-900">Paketi</h2>
          <p className="mt-0.5 text-sm text-leaf-700">Chagua fungu, kisha lipa ili upate voucher.</p>
        </div>
        <ul className="space-y-2">
          {boot.plans.map((item) => {
            const selected = plan?.id === item.id;
            return (
              <li key={item.id}>
                <button
                  type="button"
                  onClick={() => setPlan(item)}
                  className={`w-full rounded-xl border px-3 py-2.5 text-left ${
                    selected ? 'border-leaf-600 bg-leaf-100' : 'border-leaf-200 bg-white'
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className="font-semibold text-leaf-900">{item.name}</span>
                    <span className="shrink-0 font-bold text-leaf-700">
                      {formatPrice(item.price_minor, boot.branding.currency)}
                    </span>
                  </div>
                  <p className="mt-0.5 text-xs text-leaf-700">
                    {item.description || item.billing_period_label}
                  </p>
                  <p className="mt-1 text-xs text-leaf-600">
                    {formatDuration(item.validity_seconds) ?? '—'}
                    {' · '}
                    {formatBytes(item.data_cap_bytes) ?? 'Unlimited'}
                    {' · '}
                    {item.device_limit === 1 ? '1 kifaa' : `${item.device_limit} vifaa`}
                  </p>
                </button>
              </li>
            );
          })}
        </ul>
        {plan && (boot.capabilities.online_payments || boot.capabilities.demo_checkout) && (
          <form onSubmit={onBuy} className="space-y-2">
            {boot.capabilities.online_payments && (
              <input
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                inputMode="tel"
                className="portal-field"
                placeholder="07XXXXXXXX"
                aria-label="Namba ya simu"
              />
            )}
            {!boot.capabilities.online_payments && (
              <p className="text-xs text-leaf-700">
                Malipo ya simu hayajawezeshwa. Gusa ili upate voucher ya {plan.name}.
              </p>
            )}
            <button
              type="submit"
              disabled={busy || (boot.capabilities.online_payments && phone.length < 9)}
              className="portal-cta portal-btn w-full bg-leaf-600 text-white disabled:opacity-45"
            >
              {busy ? 'Inasubiri…' : `Lipa ${formatPrice(plan.price_minor, boot.branding.currency)} — ${plan.name}`}
            </button>
          </form>
        )}
      </section>

      <form onSubmit={onRedeem} className="portal-block">
        <div>
          <h2 className="text-sm font-semibold text-leaf-800">Voucher yako</h2>
          <p className="mt-0.5 text-sm text-leaf-700">Andika namba au scan QR ili uungane.</p>
        </div>
        <div className="flex items-end gap-2.5">
          <label className="block min-w-0 flex-1 text-sm font-medium text-leaf-800">
            Namba ya voucher
            <input
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              autoComplete="off"
              autoCapitalize="characters"
              inputMode="text"
              className="portal-field mt-1.5 font-mono tracking-[0.1em] placeholder:tracking-normal placeholder:text-leaf-300"
              placeholder="XXXXX-XXXXX"
            />
          </label>
          <button type="button" onClick={() => setScanTarget('main')} className="portal-btn-secondary shrink-0">
            Scan
          </button>
        </div>
        <button
          type="submit"
          disabled={busy || code.length < 6}
          className="portal-cta portal-btn w-full bg-leaf-600 text-white enabled:hover:bg-leaf-700 disabled:opacity-45"
        >
          {busy ? 'Inaunganisha…' : 'Pokea Wi‑Fi'}
        </button>
      </form>

      <section className="portal-block">
        <div>
          <h2 className="portal-brand text-lg text-leaf-900">Ongeza kifaa</h2>
          <p className="mt-0.5 text-sm text-leaf-700">TV au laptop — MAC na voucher yake yenyewe.</p>
        </div>

        {nearby.length > 0 && (
          <div className="flex gap-2">
            {nearby.map((host) => (
              <button
                key={host.mac}
                type="button"
                onClick={() => setDeviceMac(host.mac)}
                className={`min-h-10 flex-1 truncate rounded-xl border px-2.5 text-sm ${
                  deviceMac === host.mac
                    ? 'border-leaf-600 bg-leaf-100 text-leaf-900'
                    : 'border-leaf-200 bg-white text-leaf-700'
                }`}
              >
                {host.vendor || host.mac}
              </button>
            ))}
          </div>
        )}

        <form onSubmit={bindDevice} className="space-y-2.5">
          <label className="block text-sm font-medium text-leaf-800">
            MAC address
            <input
              value={deviceMac}
              onChange={(e) => setDeviceMac(e.target.value)}
              placeholder="AA:BB:CC:DD:EE:FF"
              className="portal-field mt-1.5 font-mono text-sm"
            />
          </label>
          <label className="block text-sm font-medium text-leaf-800">
            Voucher ya kifaa
            <div className="mt-1.5 flex gap-2.5">
              <input
                value={deviceCode}
                onChange={(e) => setDeviceCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                placeholder="XXXXX-XXXXX"
                className="portal-field min-w-0 flex-1 font-mono text-sm tracking-[0.08em] placeholder:tracking-normal"
              />
              <button type="button" onClick={() => setScanTarget('device')} className="portal-btn-secondary shrink-0">
                Scan
              </button>
            </div>
          </label>
          <button
            type="submit"
            disabled={busy || deviceMac.length < 11 || deviceCode.length < 6}
            className="portal-btn w-full bg-leaf-600 text-white disabled:opacity-45"
          >
            {busy ? 'Inaunganisha…' : 'Unganisha kifaa'}
          </button>
        </form>
      </section>

      <p className="portal-foot mt-auto text-center text-xs tracking-wide text-leaf-600/80">Powered by Kasi-Net</p>
    </Shell>
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
    </>
  );
}

function Shell({ children, hero }: { children: ReactNode; hero: ReactNode }) {
  return (
    <main className="portal-shell">
      <div className="portal-hero">
        <picture>
          <source srcSet="/bg-hero.webp" type="image/webp" />
          <img src="/Bg.png" alt="" decoding="async" />
        </picture>
        <div className="portal-hero-shade" aria-hidden />
        <div className="portal-hero-copy">{hero}</div>
      </div>
      <div className="portal-sheet">{children}</div>
    </main>
  );
}

