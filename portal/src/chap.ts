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
  chapId?: string | null,
  chapChallenge?: string | null,
  dst?: string | null,
): void {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = linkLogin;

  const fields: Record<string, string> = {
    username,
    password: chapId && chapChallenge ? chapMd5(chapId, password, chapChallenge) : password,
    dst: dst || '',
    popup: 'true',
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
