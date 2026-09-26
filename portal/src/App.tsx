import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import {
  formatPrice,
  portalApi,
  type Bootstrap,
  type Plan,
} from './api';
import { submitHotspotLogin } from './chap';

type View = 'packages' | 'connect';

function query(): URLSearchParams {
  return new URLSearchParams(window.location.search);
}

function pathCode(): string | null {
  const match = window.location.pathname.match(/^\/r\/([A-Z0-9-]+)/i);
  return match?.[1] ?? null;
}

function initialView(): View {
  if (pathCode()) {
    return 'connect';
  }
  return 'packages';
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
  const [deviceMac, setDeviceMac] = useState('');
  const [deviceCode, setDeviceCode] = useState('');
  const [autoRedeemed, setAutoRedeemed] = useState(false);
  const [view, setView] = useState<View>(() => initialView());

  useEffect(() => {
    const mikrotikError = params.get('error');
    if (mikrotikError && !mikrotikError.includes('$(') && mikrotikError.trim() !== '') {
      setError(mikrotikError);
    }
  }, [params]);

  const missingSite = site
    ? null
    : pathCode()
      ? 'Ungana na Wi‑Fi kwanza, kisha fungua vocha tena.'
      : 'Fungua ukurasa huu kutoka dirisha la Wi‑Fi.';

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

  function loginWith(voucherCode: string) {
    const link = params.get('link_login');
    if (!link || link.includes('$(')) {
      setError('Fungua ukurasa huu kutoka Wi‑Fi, kisha bonyeza Unganishwa tena.');
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
        setStatus('Angalia simu, thibitisha malipo…');
        const created = await portalApi.createOrder(boot.token, plan.id, phone);
        for (let i = 0; i < 45; i++) {
          await new Promise((r) => setTimeout(r, 2000));
          const current = await portalApi.order(boot.token, created.data.uuid);
          if (current.data.code) {
            loginWith(current.data.code);
            return;
          }
          if (['failed', 'expired', 'voided'].includes(current.data.status)) {
            throw new Error(current.data.status_label || 'Malipo hayajakamilika.');
          }
        }
        throw new Error('Bado tunasubiri malipo. Jaribu tena.');
      }

      if (boot.capabilities.demo_checkout) {
        setStatus('Vocha inatengenezwa…');
        const issued = await portalApi.checkout(boot.token, plan.id);
        setCode(issued.display_code || issued.code);
        await redeemCode(issued.code);
        return;
      }

      throw new Error('Lipia kwa mhudumu, kisha weka namba ya vocha.');
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
      setStatus('Kifaa kimeunganishwa. Kitaingia peke yake.');
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
            <p className="portal-kicker">Kasi-Net</p>
            <h1 className="portal-brand mt-1 text-2xl leading-none">Inaunganisha…</h1>
          </>
        }
      >
        <p className="text-center text-sm text-leaf-700">Subiri kidogo.</p>
      </Shell>
    );
  }

  if (!boot) {
    return (
      <Shell
        hero={
          <>
            <p className="portal-kicker">Kasi-Net</p>
            <h1 className="portal-brand mt-1 text-2xl leading-none">Karibu mtandaoni</h1>
          </>
        }
      >
        <p className="text-center text-sm leading-snug text-leaf-800">{error || missingSite}</p>
      </Shell>
    );
  }

  const canPay = boot.capabilities.online_payments || boot.capabilities.demo_checkout;

  return (
    <Shell
      hero={
        <>
          <p className="portal-kicker">Kasi-Net</p>
          <h1 className="portal-brand mt-1 text-[2.05rem] leading-none">
            {boot.branding.operator || 'Karibu mtandaoni'}
          </h1>
          {(boot.site.ssid || boot.branding.support_phone) && (
            <p className="mt-1.5 text-sm text-white/90">
              {boot.site.ssid}
              {boot.site.ssid && boot.branding.support_phone ? ' · ' : ''}
              {boot.branding.support_phone ? `Msaada ${boot.branding.support_phone}` : ''}
            </p>
          )}
        </>
      }
    >
      {(error || status) && (
        <p
          className={`shrink-0 rounded-xl px-3 py-2 text-center text-sm ${
            error ? 'bg-red-50 text-red-800' : 'bg-leaf-100 text-leaf-800'
          }`}
          role={error ? 'alert' : undefined}
        >
          {error || status}
        </p>
      )}

      {view === 'packages' && (
        <section className="portal-fit">
          <h2 className="portal-panel-title">Vifurushi</h2>
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
                    <span className="font-semibold text-leaf-900">{item.name}</span>
                    <span className="shrink-0 font-bold text-leaf-700">
                      {formatPrice(item.price_minor, boot.branding.currency)}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>

          {plan && canPay && (
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
              <button
                type="submit"
                disabled={busy || (boot.capabilities.online_payments && phone.length < 9)}
                className="portal-btn w-full bg-leaf-600 text-white disabled:opacity-45"
              >
                {busy ? 'Subiri…' : `Lipia ${formatPrice(plan.price_minor, boot.branding.currency)}`}
              </button>
            </form>
          )}

          <button
            type="button"
            onClick={() => {
              setError(null);
              setStatus(null);
              setView('connect');
            }}
            className="portal-btn mt-auto w-full bg-leaf-600 text-white"
          >
            Unganishwa
          </button>
        </section>
      )}

      {view === 'connect' && (
        <div className="portal-fit">
          <button type="button" className="portal-back" onClick={() => setView('packages')}>
            ← Rudi
          </button>

          <form onSubmit={onRedeem} className="portal-block">
            <h2 className="portal-panel-title">Vocha</h2>
            <label className="block text-sm font-medium text-leaf-800">
              Namba ya vocha
              <input
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                className="portal-field mt-1 font-mono tracking-[0.08em] placeholder:tracking-normal placeholder:text-leaf-300"
                placeholder="XXXXX-XXXXX"
              />
            </label>
            <button
              type="submit"
              disabled={busy || code.length < 6}
              className="portal-btn w-full bg-leaf-600 text-white disabled:opacity-45"
            >
              {busy ? 'Inaunganisha…' : 'Unganishwa'}
            </button>
          </form>

          <form onSubmit={bindDevice} className="portal-block portal-block-split">
            <h2 className="portal-panel-title">Kifaa kingine</h2>
            <p className="text-sm leading-snug text-leaf-700">TV, kompyuta au simu nyingine.</p>
            <label className="block text-sm font-medium text-leaf-800">
              Namba ya MAC
              <input
                value={deviceMac}
                onChange={(e) => setDeviceMac(e.target.value)}
                placeholder="AA:BB:CC:DD:EE:FF"
                className="portal-field mt-1 font-mono text-sm"
              />
            </label>
            <label className="block text-sm font-medium text-leaf-800">
              Namba ya vocha
              <input
                value={deviceCode}
                onChange={(e) => setDeviceCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                placeholder="XXXXX-XXXXX"
                className="portal-field mt-1 font-mono text-sm tracking-[0.08em] placeholder:tracking-normal"
              />
            </label>
            <button
              type="submit"
              disabled={busy || deviceMac.length < 11 || deviceCode.length < 6}
              className="portal-btn w-full bg-leaf-700 text-white disabled:opacity-45"
            >
              {busy ? 'Subiri…' : 'Unganisha kifaa'}
            </button>
          </form>
        </div>
      )}
    </Shell>
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
