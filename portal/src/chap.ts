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

/**
 * POST credentials to the hotspot login URL (link-login-only). Uses CHAP when
 * the captive portal supplied chap-id / chap-challenge; otherwise PAP.
 * Does not rely on router go.html, which is often not uploaded.
 */
export function submitHotspotLogin(
  linkLogin: string,
  username: string,
  password: string,
  chapId?: string | null,
  chapChallenge?: string | null,
  dst?: string | null,
): void {
  const action = linkLogin;
  const connected = `${window.location.origin}${import.meta.env.BASE_URL}connected.html`;
  const destination = dst && dst.length > 0 ? dst : connected;

  let pass = password;
  if (chapId && chapChallenge && !chapId.includes('$(') && !chapChallenge.includes('$(')) {
    pass = chapMd5(chapId, password, chapChallenge);
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = action;
  form.style.display = 'none';

  const fields: Record<string, string> = {
    username,
    password: pass,
    dst: destination,
    popup: 'false',
  };

  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }

  document.body.appendChild(form);
  form.submit();
}
