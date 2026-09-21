import { useEffect, useMemo, useState, type FormEvent } from 'react';
import {
  formatBytes,
  formatDuration,
  formatPrice,
  portalApi,
  type Bootstrap,
  type NearbyDevice,
  type Plan,
  type RedeemSession,
} from './api';
import { submitHotspotLogin } from './chap';

type Lang = 'en' | 'sw';

const t = {
  en: {
    loading: 'Loading packages…',
    wifi: 'Wi-Fi login',
    openHint: 'Open this page from the hotspot, or use http://192.168.88.10:5173/?site=kasi-demo-site-1',
    pick: 'Choose a package, pay, or enter a voucher to get online.',
    step1: '1. Choose',
    step2: '2. Pay',
    step3: '3. Connect',
    network: 'Network',
    place: 'Location',
    device: 'This device',
    packages: 'Packages',
    packagesHint: 'Tap the bundle you want. Price is for this hotspot.',
    valid: 'Valid',
    data: 'Data',
    unlimited: 'Unlimited data',
    speed: 'Speed',
    fullSpeed: 'Full speed',
    devices: 'Devices',
    phone1: '1 device',
    thenSlow: 'After the data is used, speed is reduced — you stay online.',
    thenOff: 'Connection stops when the package finishes.',
    useWithin: 'Use {t} within the validity window.',
    payTitle: 'Pay with mobile money',
    payHint: 'M-Pesa, Mixx, Airtel Money or Tigo Pesa. Use the phone that will receive the prompt.',
    phone: 'Phone number',
    payBtn: 'Pay and connect',
    waiting: 'Waiting for payment…',
    attendantTitle: 'Buy from the attendant',
    attendantBody:
      'Online payment is not enabled here yet. Tell the attendant the package. They give you a printed voucher — type it below.',
    askFor: 'Ask for',
    voucherTitle: 'I already have a voucher',
    voucherHint: 'Type the code from your card or SMS.',
    voucherCode: 'Voucher code',
    goOnline: 'Go online',
    connecting: 'Connecting…',
    extraTitle: 'TV, decoder or PlayStation',
    extraHint: 'After you go online, bind another device to the same voucher.',
    redeemFirst: 'Go online with a voucher first, then add the extra device.',
    nearby: 'Other devices on this Wi-Fi',
    noneNearby: 'No extra device found yet. Type the MAC from the TV network settings.',
    mac: 'MAC address',
    label: 'Name (TV, decoder…)',
    addDevice: 'Add device',
    useThis: 'Use this',
    helpTitle: 'Need help?',
    helpBody: 'The attendant can print a voucher or check your package.',
    call: 'Call',
    wa: 'WhatsApp',
    connected: 'You are connecting',
    keepWifi: 'Keep this Wi-Fi on. Do not switch networks.',
    extraOk: 'You can still add a TV or decoder below.',
    otherLang: 'Kiswahili',
  },
  sw: {
    loading: 'Inapakia paketi…',
    wifi: 'Kuingia Wi-Fi',
    openHint: 'Fungua ukurasa huu kutoka Wi-Fi, au tumia http://192.168.88.10:5173/?site=kasi-demo-site-1',
    pick: 'Chagua paketi, lipa, au weka vocha ili uingie mtandaoni.',
    step1: '1. Chagua',
    step2: '2. Lipa',
    step3: '3. Unganisha',
    network: 'Mtandao',
    place: 'Eneo',
    device: 'Kifaa hiki',
    packages: 'Paketi',
    packagesHint: 'Gusa fungu unalotaka. Bei ni ya hotspot hii.',
    valid: 'Muda',
    data: 'Data',
    unlimited: 'Data isiyo na kikomo',
    speed: 'Kasi',
    fullSpeed: 'Kasi kamili',
    devices: 'Vifaa',
    phone1: 'Kifaa 1',
    thenSlow: 'Data ikisha, kasi inapungua — unabakia mtandaoni.',
    thenOff: 'Muunganisho unaisha paketi ikisha.',
    useWithin: 'Tumia {t} ndani ya muda wa vocha.',
    payTitle: 'Lipa kwa simu',
    payHint: 'M-Pesa, Mixx, Airtel Money au Tigo Pesa. Weka namba itakayopokea ujumbe.',
    phone: 'Namba ya simu',
    payBtn: 'Lipa na unganisha',
    waiting: 'Inasubiri malipo…',
    attendantTitle: 'Nunua kwa mhudumu',
    attendantBody:
      'Malipo ya mtandaoni hayajawezeshwa. Mwambie mhudumu paketi. Atakupa vocha — iandike hapa chini.',
    askFor: 'Omba',
    voucherTitle: 'Nina vocha tayari',
    voucherHint: 'Andika namba iliyo kwenye kadi au SMS.',
    voucherCode: 'Namba ya vocha',
    goOnline: 'Ingia mtandaoni',
    connecting: 'Inaunganisha…',
    extraTitle: 'TV, decoder au PlayStation',
    extraHint: 'Baada ya kuingia, ongeza kifaa kingine kwenye vocha ileile.',
    redeemFirst: 'Ingia kwanza kwa vocha, kisha ongeza TV.',
    nearby: 'Vifaa vingine kwenye Wi-Fi hii',
    noneNearby: 'Hakuna kifaa kingine. Andika MAC kutoka mipangilio ya TV.',
    mac: 'Anwani ya MAC',
    label: 'Jina (TV, decoder…)',
    addDevice: 'Ongeza kifaa',
    useThis: 'Tumia hiki',
    helpTitle: 'Unahitaji msaada?',
    helpBody: 'Mhudumu anaweza kutoa vocha au kuangalia paketi yako.',
    call: 'Piga',
    wa: 'WhatsApp',
    connected: 'Unaunganishwa',
    keepWifi: 'Baki kwenye Wi-Fi hii. Usibadilishe mtandao.',
    extraOk: 'Bado unaweza kuongeza TV au decoder chini.',
    otherLang: 'English',
  },
};

function query(): URLSearchParams {
  return new URLSearchParams(window.location.search);
}

function pathCode(): string | null {
  const match = window.location.pathname.match(/^\/r\/([A-Z0-9-]+)/i);
  return match?.[1] ?? null;
}

function speedLabel(kbps: number | null | undefined): string | null {
  if (!kbps) {
    return null;
  }
  if (kbps >= 1024) {
    return `${(kbps / 1024).toFixed(kbps % 1024 === 0 ? 0 : 1)} Mbps`;
  }
  return `${kbps} kbps`;
}

function waLink(phone: string): string {
  const digits = phone.replace(/\D/g, '');
  const intl = digits.startsWith('0') ? `255${digits.slice(1)}` : digits;
  return `https://wa.me/${intl}`;
}

function telLink(phone: string): string {
  const digits = phone.replace(/\D/g, '');
  return `tel:+${digits.startsWith('0') ? `255${digits.slice(1)}` : digits}`;
}

export function App() {
  const params = useMemo(() => query(), []);
  const site = params.get('site');
  const [lang, setLang] = useState<Lang>('en');
  const [boot, setBoot] = useState<Bootstrap | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [code, setCode] = useState(pathCode() ?? '');
  const [busy, setBusy] = useState(false);
  const [phone, setPhone] = useState('');
  const [plan, setPlan] = useState<Plan | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [redeemed, setRedeemed] = useState<string | null>(null);
  const [session, setSession] = useState<RedeemSession | null>(null);
  const [hosts, setHosts] = useState<NearbyDevice[]>([]);
  const [manualMac, setManualMac] = useState('');
  const [deviceLabel, setDeviceLabel] = useState('TV');

  const copy = t[lang];
  const missingSite = site
    ? null
    : pathCode()
      ? lang === 'sw'
        ? 'Ungana Wi-Fi ya hotspot kwanza, kisha changanua vocha tena.'
        : 'Join this hotspot Wi-Fi first, then scan the voucher again.'
      : copy.openHint;

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
        document.title = `${data.branding.operator} — Wi-Fi`;
        if (data.plans[0]) {
          setPlan(data.plans[0]);
        }
        if (data.capabilities.device_discovery) {
          portalApi.nearby(data.token).then((result) => setHosts(result.data)).catch(() => undefined);
        }
      })
      .catch((err: Error) => setError(err.message));
  }, [params, site]);

  function loginWith(voucherCode: string) {
    const link = params.get('link_login');
    if (!link) {
      setStatus(copy.keepWifi);
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
      setSession(result.session ?? null);
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
    setStatus(copy.waiting);
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

  async function bindMac(mac: string) {
    if (!boot || !redeemed) {
      return;
    }
    setBusy(true);
    try {
      await portalApi.bind(boot.token, redeemed, mac, deviceLabel || undefined);
      setStatus(`${deviceLabel || 'Device'} ${mac}`);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (!boot && !error && !missingSite) {
    return (
      <main className="blank">
        <p>{copy.loading}</p>
      </main>
    );
  }

  if (!boot) {
    return (
      <main className="blank">
        <div className="card" style={{ width: '100%', maxWidth: '26rem' }}>
          <p className="kicker" style={{ color: '#1e3a8a' }}>
            Kasi Network
          </p>
          <h1 style={{ margin: '0.35rem 0 0', fontSize: '1.6rem' }}>{copy.wifi}</h1>
          <p className="muted">{error || missingSite}</p>
          {code && <p style={{ marginTop: '0.75rem', fontFamily: 'ui-monospace, Consolas, monospace' }}>{code}</p>}
          <div className="lang">
            <button type="button" className={lang === 'en' ? 'on' : ''} onClick={() => setLang('en')}>
              English
            </button>
            <button type="button" className={lang === 'sw' ? 'on' : ''} onClick={() => setLang('sw')}>
              Kiswahili
            </button>
          </div>
        </div>
      </main>
    );
  }

  const support = boot.branding.support_phone;

  return (
    <main className="page">
      <header className="topbar">
        <div className="wrap">
          <p className="kicker">{boot.site.ssid || 'Wi-Fi'}</p>
          <h1>{boot.branding.operator}</h1>
          <p className="sub">{copy.pick}</p>
          <div className="lang">
            <button type="button" className={lang === 'en' ? 'on' : ''} onClick={() => setLang('en')}>
              English
            </button>
            <button type="button" className={lang === 'sw' ? 'on' : ''} onClick={() => setLang('sw')}>
              Kiswahili
            </button>
          </div>
        </div>
      </header>

      <div className="shell">
        <ol className="steps">
          <li>{copy.step1}</li>
          <li>{copy.step2}</li>
          <li>{copy.step3}</li>
        </ol>

        <section className="card">
          <div className="meta">
            <span>
              {copy.network}: <b>{boot.site.ssid || '—'}</b>
            </span>
            <span>
              {copy.place}: <b>{boot.site.name}</b>
            </span>
            {boot.client.mac && (
              <span>
                {copy.device}: <b style={{ fontFamily: 'ui-monospace, Consolas, monospace' }}>{boot.client.mac}</b>
              </span>
            )}
          </div>
        </section>

        {error && (
          <p className="alert alert-bad" role="alert">
            {error}
          </p>
        )}
        {status && <p className="alert alert-info">{status}</p>}

        {redeemed && (
          <section className="card" style={{ background: '#dcfce7', borderColor: '#86efac' }}>
            <h2 style={{ color: '#14532d' }}>{copy.connected}</h2>
            <p className="muted" style={{ color: '#166534' }}>
              {copy.keepWifi}
            </p>
            {session && (
              <div className="specs">
                <span>{session.plan ?? 'Wi-Fi'}</span>
                <span>
                  {copy.valid} {formatDuration(session.validity_seconds) ?? '—'}
                </span>
                <span>{formatBytes(session.data_cap_bytes) ?? copy.unlimited}</span>
                <span>
                  {session.device_limit === 1 ? copy.phone1 : `${session.device_limit} ${copy.devices.toLowerCase()}`}
                </span>
              </div>
            )}
            <p className="note">{copy.extraOk}</p>
          </section>
        )}

        <section className="card" id="packages">
          <h2>{copy.packages}</h2>
          <p className="muted">{copy.packagesHint}</p>
          <ul className="plans">
            {boot.plans.map((item) => {
              const selected = plan?.id === item.id;
              const down = speedLabel(item.rate_limit_down_kbps);
              const up = speedLabel(item.rate_limit_up_kbps);
              return (
                <li key={item.id}>
                  <button type="button" className={selected ? 'plan on' : 'plan'} onClick={() => setPlan(item)}>
                    <div className="plan-head">
                      <div>
                        <strong>{item.name}</strong>
                        <p className="note" style={{ marginTop: '0.2rem' }}>
                          {item.description || item.billing_period_label}
                        </p>
                      </div>
                      <p className="price">{formatPrice(item.price_minor, boot.branding.currency)}</p>
                    </div>
                    <div className="specs">
                      <span>
                        {copy.valid}: {formatDuration(item.validity_seconds) ?? '—'}
                      </span>
                      <span>{formatBytes(item.data_cap_bytes) ?? copy.unlimited}</span>
                      <span>
                        {copy.speed}: {down ? (up ? `${down} / ${up}` : down) : copy.fullSpeed}
                      </span>
                      <span>
                        {item.device_limit === 1 ? copy.phone1 : `${item.device_limit} ${copy.devices.toLowerCase()}`}
                      </span>
                    </div>
                    {item.duration_seconds ? (
                      <p className="note">{copy.useWithin.replace('{t}', formatDuration(item.duration_seconds) ?? '')}</p>
                    ) : (
                      <p className="note">{item.on_quota_exhausted === 'throttle' ? copy.thenSlow : copy.thenOff}</p>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        </section>

        {boot.capabilities.online_payments ? (
          <section className="card" id="pay">
            <h2>{copy.payTitle}</h2>
            <p className="muted">{copy.payHint}</p>
            {plan && (
              <p className="muted">
                <b>
                  {plan.name} — {formatPrice(plan.price_minor, boot.branding.currency)}
                </b>
              </p>
            )}
            <form onSubmit={onBuy}>
              <label className="field">
                <span>{copy.phone}</span>
                <input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  inputMode="tel"
                  autoComplete="tel"
                  placeholder="07XXXXXXXX"
                />
              </label>
              <button type="submit" className="btn" disabled={busy || !plan || phone.length < 9}>
                {busy ? copy.waiting : copy.payBtn}
              </button>
            </form>
          </section>
        ) : (
          <section className="card" id="pay">
            <h2>{copy.attendantTitle}</h2>
            <p className="muted">{copy.attendantBody}</p>
            {plan && (
              <p className="alert alert-info" style={{ marginTop: '0.8rem' }}>
                {copy.askFor} <b>{plan.name}</b> — {formatPrice(plan.price_minor, boot.branding.currency)}
              </p>
            )}
          </section>
        )}

        <section className="card" id="voucher">
          <h2>{copy.voucherTitle}</h2>
          <p className="muted">{copy.voucherHint}</p>
          <form onSubmit={onRedeem}>
            <label className="field">
              <span>{copy.voucherCode}</span>
              <input
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                inputMode="text"
                placeholder="KAS-XXXX-XXXX-XX"
                style={{ fontFamily: 'ui-monospace, Consolas, monospace', letterSpacing: '0.06em', fontSize: '1.1rem' }}
              />
            </label>
            <button type="submit" className="btn btn-dark" disabled={busy || code.length < 6}>
              {busy ? copy.connecting : copy.goOnline}
            </button>
          </form>
        </section>

        <section className="card" id="devices">
          <h2>{copy.extraTitle}</h2>
          <p className="muted">{copy.extraHint}</p>
          {!redeemed && <p className="alert alert-warn" style={{ marginTop: '0.75rem' }}>{copy.redeemFirst}</p>}

          {boot.capabilities.device_discovery && (
            <>
              <p className="muted" style={{ fontWeight: 700 }}>
                {copy.nearby}
              </p>
              {hosts.length === 0 && <p className="note">{copy.noneNearby}</p>}
              {hosts.map((host) => (
                <button
                  key={host.mac}
                  type="button"
                  className="host"
                  disabled={!redeemed || busy}
                  onClick={() => void bindMac(host.mac)}
                >
                  <span>
                    <b>{host.vendor || host.ip || 'Device'}</b>
                    <small>{host.mac}</small>
                  </span>
                  {copy.useThis}
                </button>
              ))}
            </>
          )}

          <form
            onSubmit={(e) => {
              e.preventDefault();
              void bindMac(manualMac);
            }}
          >
            <label className="field">
              <span>{copy.label}</span>
              <input value={deviceLabel} onChange={(e) => setDeviceLabel(e.target.value)} placeholder="TV" />
            </label>
            <label className="field">
              <span>{copy.mac}</span>
              <input
                value={manualMac}
                onChange={(e) => setManualMac(e.target.value)}
                placeholder="AA:BB:CC:DD:EE:FF"
                style={{ fontFamily: 'ui-monospace, Consolas, monospace' }}
              />
            </label>
            <button type="submit" className="btn" disabled={!redeemed || busy || manualMac.length < 12}>
              {copy.addDevice}
            </button>
          </form>
        </section>

        <section className="card" id="help">
          <h2>{copy.helpTitle}</h2>
          <p className="muted">{copy.helpBody}</p>
          {support && (
            <div className="help-actions">
              <a className="call" href={telLink(support)}>
                {copy.call} {support}
              </a>
              <a className="wa" href={waLink(support)} target="_blank" rel="noreferrer">
                {copy.wa}
              </a>
            </div>
          )}
        </section>
      </div>
    </main>
  );
}
