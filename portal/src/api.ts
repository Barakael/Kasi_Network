export type Branding = {
  operator: string;
  primary_color: string | null;
  support_phone: string | null;
  currency: string;
};

export type Plan = {
  id: number;
  name: string;
  description: string | null;
  billing_period_label: string;
  validity_seconds: number;
  duration_seconds: number | null;
  data_cap_bytes: number | null;
  price_minor: number;
  rate_limit_down_kbps: number | null;
  rate_limit_up_kbps?: number | null;
  device_limit: number;
  on_quota_exhausted?: string;
  throttle_down_kbps?: number | null;
};

export type RedeemSession = {
  plan: string | null;
  validity_seconds: number;
  duration_seconds: number | null;
  data_cap_bytes: number | null;
  device_limit: number;
  expires_at: string | null;
};

export type RedeemResult = {
  code: string;
  display_code: string;
  session?: RedeemSession;
};

export type Bootstrap = {
  token: string;
  site: { name: string; ssid: string | null };
  branding: Branding;
  client: { mac: string | null; mac_known: boolean };
  capabilities: { online_payments: boolean; device_discovery: boolean };
  plans: Plan[];
};

export type NearbyDevice = {
  mac: string;
  ip: string | null;
  vendor: string | null;
};

export type Order = {
  uuid: string;
  status: string;
  status_label: string;
  code?: string;
  display_code?: string;
};

function authHeaders(token?: string): HeadersInit {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { 'X-Kasi-Portal-Token': token } : {}),
  };
}

async function parse<T>(res: Response): Promise<T> {
  const body = (await res.json().catch(() => ({}))) as {
    message?: string;
    errors?: Record<string, string[]>;
  };

  if (!res.ok) {
    throw new Error(body.message || body.errors?.code?.[0] || body.errors?.phone?.[0] || 'Something went wrong.');
  }

  return body as T;
}

export const portalApi = {
  bootstrap: (payload: Record<string, string>) =>
    fetch('/api/portal/bootstrap', {
      method: 'POST',
      headers: authHeaders(),
      body: JSON.stringify(payload),
    }).then((res) => parse<Bootstrap>(res)),

  redeem: (token: string, code: string) =>
    fetch('/api/portal/redeem', {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify({ code }),
    }).then((res) => parse<RedeemResult>(res)),

  createOrder: (token: string, planId: number, phone: string) =>
    fetch('/api/portal/orders', {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify({ plan_id: planId, phone }),
    }).then((res) => parse<{ data: Order }>(res)),

  order: (token: string, uuid: string) =>
    fetch(`/api/portal/orders/${uuid}`, { headers: authHeaders(token) }).then((res) =>
      parse<{ data: Order }>(res),
    ),

  nearby: (token: string) =>
    fetch('/api/portal/devices/nearby', { headers: authHeaders(token) }).then((res) =>
      parse<{ data: NearbyDevice[] }>(res),
    ),

  bind: (token: string, code: string, mac: string, label?: string) =>
    fetch('/api/portal/devices', {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify({ code, mac, label }),
    }).then((res) => parse<unknown>(res)),
};

export function formatPrice(minor: number, currency: string): string {
  return `${minor.toLocaleString()} ${currency}`;
}

export function formatBytes(bytes: number | null): string | null {
  if (bytes === null) {
    return null;
  }
  if (bytes >= 1_000_000_000) {
    return `${Number((bytes / 1_000_000_000).toFixed(2)).toString()} GB`;
  }
  return `${Math.round(bytes / 1_000_000)} MB`;
}

export function formatDuration(seconds: number | null): string | null {
  if (seconds === null || seconds <= 0) {
    return null;
  }
  if (seconds % 86400 === 0) {
    const days = seconds / 86400;
    return days === 1 ? '1 day' : `${days} days`;
  }
  if (seconds % 3600 === 0) {
    const hours = seconds / 3600;
    return hours === 1 ? '1 hour' : `${hours} hours`;
  }
  if (seconds >= 3600) {
    return `${Math.round(seconds / 3600)} hours`;
  }
  return `${Math.max(1, Math.round(seconds / 60))} min`;
}
