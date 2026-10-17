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

type View = 'home' | 'voucher' | 'device';

function query(): URLSearchParams {
  return new URLSearchParams(window.location.search);
}

function pathCode(): string | null {
  const match = window.location.pathname.match(/^\/r\/([A-Z0-9-]+)/i);
  return match?.[1] ?? null;
}

function phoneDigits(value: string): string {
  return value.replace(/\D/g, '');
}

/** AA:BB:CC:DD:EE:FF while typing — hex only, uppercase, colons every two. */
function formatMacInput(raw: string): string {
  const hex = raw.replace(/[^0-9a-fA-F]/g, '').toUpperCase().slice(0, 12);
  return hex.match(/.{1,2}/g)?.join(':') ?? '';
}

function macHexLength(formatted: string): number {
  return formatted.replace(/[^0-9A-F]/gi, '').length;
}

function initialView(params: URLSearchParams): View {
  if (pathCode()) {
    return 'voucher';
  }
  const view = params.get('view');
  // Hotspot go.html still links view=packages — packages now live on home.
  if (view === 'packages') {
    return 'home';
  }
  if (view === 'voucher' || view === 'device') {
    return view;
  }
  return 'home';
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
  const [boundOk, setBoundOk] = useState<{ authorized: boolean; message: string } | null>(null);
  const [view, setView] = useState<View>(() => initialView(params));

  const missingSite = site
    ? null
    : pathCode()
      ? 'Connect to the Wi-Fi hotspot first, then open this voucher again from the login page.'
      : 'Open this page from the Wi-Fi login screen.';

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
        if (data.client.phone) {
          setPhone(data.client.phone);
        }
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

  async function ensureIdentified(token: string): Promise<string> {
    if (!boot) {
      throw new Error('Inasubiri…');
    }
    if (!boot.needs_phone) {
      return token;
    }
    if (phoneDigits(phone).length !== 10) {
      throw new Error('Weka namba ya simu kwanza.');
    }
    const identified = await portalApi.identify(token, phone);
    setBoot(identified);
    if (identified.client.phone) {
      setPhone(identified.client.phone);
    }
    return identified.token;
  }

  async function redeemCode(raw: string, tokenOverride?: string) {
    if (!boot) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      // Printed vouchers redeem by code + MAC — no phone required.
      const result = await portalApi.redeem(tokenOverride ?? boot.token, raw);
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
        const token = await ensureIdentified(boot.token);
        setStatus('Angalia simu yako kwa ujumbe wa malipo…');
        const created = await portalApi.createOrder(token, plan.id, phone);
        for (let i = 0; i < 45; i++) {
          await new Promise((r) => setTimeout(r, 2000));
          const current = await portalApi.order(token, created.data.uuid);
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
        const token = await ensureIdentified(boot.token);
        setStatus('Inatengeneza voucher…');
        const issued = await portalApi.checkout(token, plan.id);
        setCode(issued.display_code || issued.code);
        setStatus(`${issued.plan}: ${issued.display_code || issued.code}`);
        await redeemCode(issued.code, token);
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
    if (!boot || macHexLength(deviceMac) !== 12 || deviceCode.replace(/[\s-]/g, '').length < 6) {
      return;
    }
    setBusy(true);
    setError(null);
    setBoundOk(null);
    try {
      const result = await portalApi.bind(boot.token, deviceCode, deviceMac);
      setDeviceCode('');
      setBoundOk({
        authorized: result.authorized,
        message: result.message || 'Kifaa kimeunganishwa',
      });
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (!boot && !error && !missingSite) {
    return <ConnectingSplash />;
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
  const digits = phoneDigits(phone);
  const phoneReady = !!boot.client.phone || digits.length === 10;
  const canPay =
    !!plan &&
    !busy &&
    (boot.capabilities.online_payments
      ? phoneReady
      : boot.capabilities.demo_checkout);

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

      {view === 'home' && (
        <section className="portal-block portal-home">
          <div className="portal-sheet-head">
            <p className="text-xs font-semibold tracking-[0.18em] uppercase text-leaf-600">Karibu</p>
            <p className="portal-brand mt-1 text-[1.45rem] leading-none text-leaf-900">
              {boot.branding.operator || 'Kasi-Net'}
            </p>
            <p className="mt-1 text-sm text-leaf-700">Chagua bando, kisha weka namba ya simu.</p>
          </div>

          <ul className="portal-plans">
            {boot.plans.map((item) => {
              const selected = plan?.id === item.id;
              return (
                <li key={item.id}>
                  <button
                    type="button"
                    onClick={() => setPlan(item)}
                    className={`portal-plan ${selected ? 'portal-plan-on' : ''}`}
                  >
                    <span className="portal-plan-main">
                      <span className="portal-plan-name">{item.name}</span>
                      <span className="portal-plan-meta">
                        {formatDuration(item.validity_seconds) ?? '—'}
                        {' · '}
                        {formatBytes(item.data_cap_bytes) ?? 'Unlimited'}
                      </span>
                    </span>
                    <span className="portal-plan-price">
                      {formatPrice(item.price_minor, boot.branding.currency)}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>

          {(boot.capabilities.online_payments || boot.capabilities.demo_checkout) && (
            <form onSubmit={onBuy} className="portal-pay">
              {boot.capabilities.online_payments && (
                <div className="portal-phone-row">
                  <input
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    inputMode="tel"
                    autoComplete="tel"
                    className="portal-field portal-phone-input"
                    placeholder="07XXXXXXXX"
                    aria-label="Namba ya simu"
                    readOnly={!!boot.client.phone}
                  />
                  <button
                    type="submit"
                    disabled={!canPay}
                    className={`portal-send ${canPay ? 'portal-send-ready' : ''}`}
                    aria-label={busy ? 'Inasubiri…' : 'Tuma malipo'}
                  >
                    {busy ? '…' : '→'}
                  </button>
                </div>
              )}
              {!boot.capabilities.online_payments && boot.capabilities.demo_checkout && (
                <button
                  type="submit"
                  disabled={!canPay}
                  className={`portal-send portal-send-full ${canPay ? 'portal-send-ready' : ''}`}
                >
                  {busy ? 'Inasubiri…' : `Pata ${plan?.name ?? 'bando'}`}
                </button>
              )}
            </form>
          )}

          <div className="portal-secondary">
            <button type="button" className="portal-secondary-link" onClick={() => setView('voucher')}>
              <span>
                <span className="portal-choice-kicker">Nina vocha</span>
                <span className="portal-choice-title block">Tumia vocha</span>
              </span>
              <span className="portal-choice-go" aria-hidden>
                →
              </span>
            </button>
            <button type="button" className="portal-secondary-link" onClick={() => setView('device')}>
              <span>
                <span className="portal-choice-kicker">TV / laptop</span>
                <span className="portal-choice-title block">Ongeza kifaa</span>
              </span>
              <span className="portal-choice-go" aria-hidden>
                →
              </span>
            </button>
          </div>
        </section>
      )}

      {view === 'voucher' && (
        <form onSubmit={onRedeem} className="portal-block">
          <button type="button" className="portal-back" onClick={() => setView('home')}>
            ← Menu
          </button>
          <div>
            <h2 className="portal-panel-title">Tumia vocha</h2>
            <p className="mt-1 text-sm text-leaf-700">Andika namba iliyo kwenye kadi.</p>
          </div>
          <label className="block text-sm font-medium text-leaf-800">
            Namba ya vocha
            <input
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              autoComplete="off"
              autoCapitalize="characters"
              inputMode="text"
              className="portal-field mt-1.5 font-mono tracking-[0.1em] placeholder:tracking-normal placeholder:text-leaf-300"
              placeholder="XXXXXX"
            />
          </label>
          <button
            type="submit"
            disabled={busy || code.replace(/[\s-]/g, '').length < 6}
            className="portal-cta portal-btn w-full bg-leaf-600 text-white enabled:hover:bg-leaf-700 disabled:opacity-45"
          >
            Tumia vocha
          </button>
        </form>
      )}

      {view === 'device' && (
        <section className="portal-block">
          <button
            type="button"
            className="portal-back"
            onClick={() => {
              setBoundOk(null);
              setView('home');
            }}
          >
            ← Menu
          </button>
          <div>
            <h2 className="portal-panel-title">Ongeza kifaa</h2>
            <p className="mt-1 text-sm text-leaf-700">TV au laptop — MAC na voucher yake yenyewe.</p>
          </div>

          {nearby.length > 0 && (
            <div className="flex gap-2">
              {nearby.map((host) => (
                <button
                  key={host.mac}
                  type="button"
                  onClick={() => setDeviceMac(formatMacInput(host.mac))}
                  className={`min-h-10 flex-1 truncate rounded-xl border px-2.5 text-sm ${
                    deviceMac === formatMacInput(host.mac)
                      ? 'border-leaf-600 bg-leaf-100 text-leaf-900'
                      : 'border-leaf-200 bg-white text-leaf-700'
                  }`}
                >
                  {host.vendor || formatMacInput(host.mac)}
                </button>
              ))}
            </div>
          )}

          <form onSubmit={bindDevice} className="space-y-2.5">
            <label className="block text-sm font-medium text-leaf-800">
              MAC address
              <input
                value={deviceMac}
                onChange={(e) => setDeviceMac(formatMacInput(e.target.value))}
                placeholder="AA:BB:CC:DD:EE:FF"
                autoCapitalize="characters"
                autoCorrect="off"
                spellCheck={false}
                inputMode="text"
                className="portal-field mt-1.5 font-mono text-sm uppercase tracking-[0.08em]"
              />
            </label>
            <label className="block text-sm font-medium text-leaf-800">
              Voucher ya kifaa
              <input
                value={deviceCode}
                onChange={(e) => setDeviceCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                placeholder="XXXXXX"
                className="portal-field mt-1.5 font-mono text-sm tracking-[0.08em] placeholder:tracking-normal"
              />
            </label>
            <button
              type="submit"
              disabled={
                busy ||
                macHexLength(deviceMac) !== 12 ||
                deviceCode.replace(/[\s-]/g, '').length < 6
              }
              className="portal-btn w-full bg-leaf-600 text-white disabled:opacity-45"
            >
              {busy ? 'Inaunganisha…' : 'Unganisha kifaa'}
            </button>
          </form>
        </section>
      )}

      <p className="portal-foot mt-auto text-center text-xs tracking-wide text-leaf-600/80">Powered by Kasi-Net</p>
    </Shell>
      {busy && (view === 'voucher' || view === 'device') && (
        <ConnectingSplash
          brand={boot.branding.operator}
          logoUrl={boot.branding.logo_url}
          status={view === 'device' ? 'Inaunganisha kifaa…' : 'Inaunganisha…'}
        />
      )}
      {boundOk && !busy && (
        <ConnectingSplash
          brand={boot.branding.operator}
          logoUrl={boot.branding.logo_url}
          status="Kifaa kimeunganishwa"
          detail={
            boundOk.authorized
              ? 'Kifaa kinaweza kutumia intaneti sasa.'
              : 'Washa Wi‑Fi upya kwenye kifaa hicho ili kiunganishe.'
          }
          done
          onDismiss={() => {
            setBoundOk(null);
            setView('home');
          }}
        />
      )}
    </>
  );
}

function Shell({ children, hero }: { children: ReactNode; hero: ReactNode }) {
  const asset = (path: string) => `${import.meta.env.BASE_URL}${path.replace(/^\//, '')}`;

  return (
    <main className="portal-shell">
      <div className="portal-hero">
        <picture>
          <source srcSet={asset('bg-hero.webp')} type="image/webp" />
          <img src={asset('Bg.png')} alt="" decoding="async" />
        </picture>
        <div className="portal-hero-shade" aria-hidden />
        <div className="portal-hero-copy">{hero}</div>
      </div>
      <div className="portal-sheet">{children}</div>
    </main>
  );
}

function ConnectingSplash({
  brand = 'HasNetworks',
  logoUrl = null,
  status = 'Inasubiri ipakie…',
  detail = null,
  done = false,
  onDismiss,
}: {
  brand?: string | null;
  logoUrl?: string | null;
  status?: string;
  detail?: string | null;
  done?: boolean;
  onDismiss?: () => void;
}) {
  return (
    <div className="portal-connect" role="status" aria-live="polite">
      <div className="portal-connect-inner">
        <div className="portal-connect-glow" aria-hidden />
        <div className="portal-connect-mark">
          {!done && <span className="portal-connect-spin" aria-hidden />}
          {logoUrl ? (
            <img src={logoUrl} alt="" />
          ) : (
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
              <circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.12)" />
              <path
                d="M32 14c8.5 6.2 12.5 12.8 12.5 19.2 0 7.4-5.6 12.8-12.5 12.8S19.5 40.6 19.5 33.2C19.5 26.8 23.5 20.2 32 14Z"
                fill="#eaf6ee"
              />
              <path d="M32 22v24" stroke="#2f5f3d" strokeWidth="2.5" strokeLinecap="round" />
              <path
                d="M22 36c6 2.5 14 2.5 20 0"
                stroke="#2f5f3d"
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.7"
              />
            </svg>
          )}
        </div>
        <p className="portal-connect-brand">{brand || 'HasNetworks'}</p>
        <p className="portal-connect-status">{status}</p>
        {detail && (
          <p className="portal-connect-status" style={{ opacity: 0.85, fontSize: '0.85rem' }}>
            {detail}
          </p>
        )}
        {done && onDismiss && (
          <button
            type="button"
            onClick={onDismiss}
            className="portal-btn"
            style={{
              marginTop: '0.5rem',
              minWidth: '8rem',
              background: '#fff',
              color: '#2f5f3d',
              border: 'none',
              cursor: 'pointer',
            }}
          >
            Sawa
          </button>
        )}
      </div>
    </div>
  );
}

