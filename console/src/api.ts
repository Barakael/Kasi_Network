const TOKEN_KEY = 'kasi.console.token';

export type Role = 'owner' | 'staff' | 'agent' | 'platform_admin';

export type Tenant = {
  uuid: string;
  name: string;
  currency: string;
  portal_name: string | null;
  accepts_online_payments: boolean;
};

export type User = {
  id: number;
  name: string;
  email: string;
  role: Role;
  role_label: string;
  tenant?: Tenant;
};

export type Plan = {
  id: number;
  name: string;
  description: string | null;
  billing_period: string;
  billing_period_label: string;
  validity_seconds: number;
  duration_seconds: number | null;
  data_cap_bytes: number | null;
  price_minor: number;
  rate_limit_down_kbps: number | null;
  rate_limit_up_kbps: number | null;
  device_limit: number;
  on_quota_exhausted: string;
  throttle_down_kbps: number | null;
  throttle_up_kbps: number | null;
  shelf_life_days?: number | null;
  is_active?: boolean;
  is_sold_online?: boolean;
  vouchers_count?: number;
};

export type Site = {
  id: number;
  name: string;
  ssid: string | null;
  nas_identifier: string;
  status: string;
  nas_devices_count?: number;
};

export type Batch = {
  id: number;
  reference: string;
  quantity: number;
  status: string;
  status_label: string;
  is_printable: boolean;
  print_count: number;
  issued_count?: number;
  redeemed_count?: number;
  unused_count?: number;
  created_at: string | null;
  plan?: Plan;
  site?: Site | null;
  assigned_agent?: User | null;
};

export type Session = {
  acctuniqueid: string;
  username: string;
  nasipaddress: string;
  callingstationid: string;
  framedipaddress: string;
  acctstarttime: string | null;
  seconds: number;
  bytes: number;
};

export type NasDevice = {
  id: number;
  name: string;
  nasname: string;
  coa_port: number;
  status: string;
  has_api: boolean;
  site?: Site;
};

export type Device = {
  id: number;
  voucher_id: number;
  mac: string;
  label: string | null;
  vendor: string | null;
  status: string;
  voucher_suffix?: string | null;
};

export type Order = {
  uuid: string;
  status: string;
  status_label: string;
  amount_minor: number;
  currency: string;
  phone: string;
  plan?: Plan;
};

export type Dashboard = {
  concurrent_sessions: number;
  revenue_today_minor: number;
  revenue_month_minor: number;
  vouchers_activated_today: number;
  revenue_series: { day: string; total: number | string }[];
};

export type Paginated<T> = {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
};

type ApiError = Error & { status?: number };

function token(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(value: string | null): void {
  if (value) {
    localStorage.setItem(TOKEN_KEY, value);
  } else {
    localStorage.removeItem(TOKEN_KEY);
  }
}

export function hasToken(): boolean {
  return token() !== null;
}

async function parse<T>(res: Response): Promise<T> {
  const contentType = res.headers.get('content-type') ?? '';
  const body = contentType.includes('json')
    ? ((await res.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
      })
    : {};

  if (res.status === 401) {
    setToken(null);
    if (!window.location.pathname.startsWith('/login')) {
      window.location.assign('/login');
    }
  }

  if (!res.ok) {
    const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    const err = new Error(body.message || firstError || 'Something went wrong.') as ApiError;
    err.status = res.status;
    throw err;
  }

  return body as T;
}

function headers(extra?: HeadersInit): HeadersInit {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
    ...extra,
  };
}

function get<T>(path: string): Promise<T> {
  return fetch(path, { headers: headers() }).then((res) => parse<T>(res));
}

function send<T>(path: string, method: string, body?: unknown): Promise<T> {
  return fetch(path, {
    method,
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  }).then((res) => parse<T>(res));
}

export const api = {
  login: (email: string, password: string) =>
    send<{ token: string; user: User }>('/api/v1/auth/login', 'POST', {
      email,
      password,
      device_name: navigator.userAgent.slice(0, 80) || 'Console',
    }),
  logout: () => send<{ message: string }>('/api/v1/auth/logout', 'POST'),
  me: async () => {
    const body = await get<unknown>('/api/v1/auth/me');
    if (body && typeof body === 'object' && 'data' in body) {
      return (body as { data: User }).data;
    }
    return body as User;
  },
  dashboard: () => get<Dashboard>('/api/v1/dashboard'),
  sessions: () => get<{ data: Session[] }>('/api/v1/sessions'),
  disconnect: (acctuniqueid: string) => send<{ disconnected: boolean }>('/api/v1/sessions/disconnect', 'POST', { acctuniqueid }),
  plans: () => get<{ data: Plan[] }>('/api/v1/plans'),
  createPlan: (payload: Record<string, unknown>) => send<{ data: Plan }>('/api/v1/plans', 'POST', payload),
  updatePlan: (id: number, payload: Record<string, unknown>) => send<{ data: Plan }>(`/api/v1/plans/${id}`, 'PATCH', payload),
  retirePlan: (id: number) => send<{ message: string }>(`/api/v1/plans/${id}`, 'DELETE'),
  batches: () => get<Paginated<Batch>>('/api/v1/voucher-batches'),
  createBatch: (payload: Record<string, unknown>) => send<{ data: Batch }>('/api/v1/voucher-batches', 'POST', payload),
  disableBatch: (id: number, reason: string) =>
    send<{ message: string }>(`/api/v1/voucher-batches/${id}/disable`, 'POST', { reason }),
  sites: () => get<{ data: Site[] }>('/api/v1/sites'),
  createSite: (payload: Record<string, unknown>) => send<{ data: Site }>('/api/v1/sites', 'POST', payload),
  nasDevices: () => get<{ data: NasDevice[] }>('/api/v1/nas-devices'),
  createNas: (payload: Record<string, unknown>) => send<{ data: NasDevice }>('/api/v1/nas-devices', 'POST', payload),
  snippet: (id: number) => get<{ rsc: string; login_html: string }>(`/api/v1/nas-devices/${id}/snippet`),
  devices: () => get<Paginated<Device>>('/api/v1/devices'),
  revokeDevice: (id: number) => send<{ message: string }>(`/api/v1/devices/${id}`, 'DELETE'),
  agents: () => get<{ data: User[] }>('/api/v1/agents'),
  revenue: () => get<{ data: { day: string; orders: number; total: number }[]; from: string; to: string }>('/api/v1/reports/revenue'),
  orders: () => get<Paginated<Order>>('/api/v1/reports/orders'),
};

export async function openPrintSheet(batchId: number): Promise<void> {
  const res = await fetch(`/api/print/voucher-batches/${batchId}?to=480`, {
    headers: {
      Accept: 'text/html',
      ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
    },
  });

  if (!res.ok) {
    const body = (await res.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
    throw new Error(body.message || body.errors?.batch?.[0] || 'Could not open the print sheet.');
  }

  const html = await res.text();
  const url = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
  window.open(url, '_blank', 'noopener');
}
