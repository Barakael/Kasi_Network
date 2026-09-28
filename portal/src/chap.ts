import { md5bytes } from './md5';

function hexToBytes(hex: string): Uint8Array {
  const clean = hex.replace(/^0x/i, '').replace(/[^0-9a-f]/gi, '');
  const out = new Uint8Array(clean.length / 2);
  for (let i = 0; i < out.length; i++) {
    out[i] = parseInt(clean.slice(i * 2, i * 2 + 2), 16);
  }
  return out;
}

function chapIdByte(chapId: string): number {
  if (chapId.startsWith('0x') || chapId.startsWith('0X')) {
    return parseInt(chapId.slice(2), 16) & 0xff;
  }
  if (chapId.length === 1) {
    return chapId.charCodeAt(0);
  }
  return parseInt(chapId, 16) & 0xff;
}

/**
 * MikroTik hotspot CHAP: MD5(chap-id || password || challenge).
 * Computed in the browser so the voucher never crosses the LAN in cleartext.
 */
export function chapMd5(chapId: string, password: string, challenge: string): string {
  const pwd = new TextEncoder().encode(password);
  const chal = hexToBytes(challenge);
  const data = new Uint8Array(1 + pwd.length + chal.length);
  data[0] = chapIdByte(chapId);
  data.set(pwd, 1);
  data.set(chal, 1 + pwd.length);
  return [...md5bytes(data)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

export function submitHotspotLogin(
  linkLogin: string,
  username: string,
  password: string,
  _chapId?: string | null,
  _chapChallenge?: string | null,
  _dst?: string | null,
): void {
  const login = new URL(linkLogin);
  const url = new URL('/go.html', login.origin);
  url.searchParams.set('username', username);
  url.searchParams.set('password', password);
  url.searchParams.set('dst', `${window.location.origin}/connected.html`);
  url.searchParams.set('popup', 'false');
  window.location.replace(url.toString());
}
