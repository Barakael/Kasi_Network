const TOKEN_KEY = 'kasi.console.token';

export type Role = 'owner' | 'agent' | 'platform_admin';

export type Tenant = {
  uuid: string;
  name: string;
  currency: string;
  portal_name: string | null;
  support_phone?: string | null;
  logo_url?: string | null;
  palmpesa_configured?: boolean;
  palmpesa_user_id?: string | null;
  palmpesa_vendor?: string | null;
  accepts_online_payments: boolean;
  status?: string;
};

export type User = {
  id: number;
  name: string;
  email: string;
  role: Role;
  role_label: string;
  phone?: string | null;
  two_factor_enabled?: boolean;
  site_ids?: number[];
  sites?: { id: number; name: string }[];
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
  offer_label?: string | null;
  offer_ends_at?: string | null;
  has_active_offer?: boolean;
  default_price_minor?: number | null;
};

export type Site = {
  id: number;
  name: string;
  abbreviation?: string | null;
  ssid: string | null;
  nas_identifier: string;
  status: string;
  nas_devices_count?: number;
  agents?: User[];
};

export type Customer = {
  id: number;
  phone: string;
  phone_local: string;
  status: 'hai' | 'kimya';
  payment?: 'paid' | 'expired' | 'new';
  site?: Site | null;
  last_mac?: string | null;
  first_seen_at?: string | null;
  last_seen_at?: string | null;
  last_package?: string | null;
  paid_via?: 'kadi' | 'simu' | null;
  has_unused_voucher?: boolean;
  session_id?: string | null;
};

export type Campaign = {
  id: number;
  title: string;
  body: string;
  audience: string;
  starts_at: string | null;
  ends_at: string | null;
  is_active: boolean;
  is_live: boolean;
};

export type PlatformInvoice = {
  id: number;
  amount_minor: number;
  currency: string;
  status: string;
  period_label: string | null;
  due_at: string | null;
  paid_at: string | null;
  tenant?: Tenant;
};

export type Collections = {
  period: string;
  lipia_minor: number;
  kadi_minor: number;
  total_minor: number;
  lipia_count: number;
  kadi_count: number;
  previous_total_minor: number;
  previous_lipia_minor: number;
  previous_kadi_minor: number;
  series?: { day: string; orders: number; total: number }[];
};

export type AgentDesk = {
  site: { id: number; name: string; ssid: string | null };
  sites: { id: number; name: string; ssid: string | null }[];
  remaining_cards: number;
  leo: { kadi_count: number; kadi_minor: number; used_count?: number };
  online: Session[];
  batches: Batch[];
  customers: Customer[];
  router_quiet?: boolean;
  last_radius_at?: string | null;
  hai_count?: number;
  kimya_count?: number;
  week?: { kadi_count: number; kadi_minor: number };
  previous_week?: { kadi_count: number; kadi_minor: number };
  series?: { day: string; orders: number; total: number }[];
};

export type PlatformOverview = {
  operators_active: number;
  operators_suspended: number;
  lipia_on: number;
  invoices_open: number;
  invoices_paid: number;
};

export type Batch = {
  id: number;
  reference: string;
  quantity: number;
  status: string;
  status_label: string;
  is_printable: boolean;
  print_count: number;
  printable_count?: number;
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
  api_host?: string | null;
  last_probe_status?: string | null;
  last_radius_at?: string | null;
  router_quiet?: boolean;
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
  lipia_today_minor?: number;
  kadi_today_minor?: number;
  unused_cards?: number;
  router_quiet?: boolean;
  last_radius_at?: string | null;
  week_minor?: number;
  previous_week_minor?: number;
  week_lipia_minor?: number;
  week_kadi_minor?: number;
  hai_count?: number;
  kimya_count?: number;
};

export type Insights = {
  period: string;
  from: string;
  to: string;
  income: {
    total_minor: number;
    lipia_minor: number;
    kadi_minor: number;
    lipia_count: number;
    kadi_count: number;
    previous_total_minor: number;
    change_pct: number | null;
    today_minor: number;
    month_minor: number;
    lifetime_minor: number;
  };
  customers: {
    total: number;
    online_now: number;
    sessions_open: number;
    hai: number;
    kimya: number;
    new_in_period: number;
    new_today: number;
    repeat_buyers: number;
    idle: number;
    idle_days: number;
  };
  stock: {
    unused: number;
    active: number;
    exhausted: number;
    disabled: number;
    expired: number;
    expired_stored: number;
    time_up: number;
    expired_value_minor: number;
    at_counter: number;
    at_counter_value_minor: number;
    in_office: number;
    expiring_soon: number;
    expiring_soon_value_minor: number;
    dead_stock: number;
    dead_stock_value_minor: number;
    soon_days: number;
  };
  agents: {
    id: number;
    name: string;
    sites: string[];
    sold_count: number;
    sold_minor: number;
    delivered_count: number;
    delivered_minor: number;
    stock: number;
  }[];
  sites: {
    id: number;
    name: string;
    total_minor: number;
    lipia_minor: number;
    kadi_minor: number;
    online: number;
    cards_left: number;
    router_quiet: boolean;
    routers: number;
  }[];
  plans: {
    id: number;
    name: string;
    price_minor: number;
    kadi_count: number;
    lipia_count: number;
    total_minor: number;
  }[];
  series: { day: string; orders: number; total: number }[];
};

export type Paginated<T> = {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
  counts?: { all: number; online: number; paid: number; expired: number };
};

export type BillingSummary = {
  days_left: number;
  days_used: number;
  anchor_at: string;
  last_paid_at: string | null;
  amount_minor: number | null;
  currency: string;
  status: string;
  payee_name: string | null;
  account_number: string | null;
  instructions: string | null;
};

export type PlatformPaymentDetails = {
  payee_name: string | null;
  account_number: string | null;
  instructions: string | null;
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
        requires_two_factor?: boolean;
      })
    : {};

  if (res.status === 401) {
    setToken(null);
    const loginPath = `${import.meta.env.BASE_URL.replace(/\/$/, '')}/login`;
    if (!window.location.pathname.startsWith(loginPath)) {
      window.location.assign(loginPath);
    }
  }

  if (!res.ok) {
    const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    const err = new Error(body.message || firstError || 'Something went wrong.') as ApiError & {
      requiresTwoFactor?: boolean;
    };
    err.status = res.status;
    if (res.status === 403 && 'requires_two_factor' in body && body.requires_two_factor) {
      err.requiresTwoFactor = true;
    }
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
  login: (email: string, password: string, twoFactorCode?: string) =>
    send<{ token: string; user: User; requires_two_factor?: boolean }>('/api/v1/auth/login', 'POST', {
      email,
      password,
      two_factor_code: twoFactorCode || undefined,
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
  startTwoFactor: () => send<{ secret: string; otpauth_url: string; qr_svg: string }>('/api/v1/auth/two-factor', 'POST'),
  confirmTwoFactor: (code: string) => send<{ two_factor_enabled: boolean }>('/api/v1/auth/two-factor/confirm', 'POST', { code }),
  disableTwoFactor: (password: string) =>
    fetch('/api/v1/auth/two-factor', {
      method: 'DELETE',
      headers: headers(),
      body: JSON.stringify({ password }),
    }).then((res) => parse<{ two_factor_enabled: boolean }>(res)),
  changePassword: (currentPassword: string, password: string, passwordConfirmation: string) =>
    send<{ message: string }>('/api/v1/auth/password', 'POST', {
      current_password: currentPassword,
      password,
      password_confirmation: passwordConfirmation,
    }),
  dashboard: () => get<Dashboard>('/api/v1/dashboard'),
  sessions: () => get<{ data: Session[] }>('/api/v1/sessions'),
  disconnect: (acctuniqueid: string) =>
    send<{ disconnected: boolean; session_closed?: boolean; mac_released?: boolean }>(
      '/api/v1/sessions/disconnect',
      'POST',
      { acctuniqueid },
    ),
  plans: () => get<{ data: Plan[] }>('/api/v1/plans'),
  createPlan: (payload: Record<string, unknown>) => send<{ data: Plan }>('/api/v1/plans', 'POST', payload),
  updatePlan: (id: number, payload: Record<string, unknown>) => send<{ data: Plan }>(`/api/v1/plans/${id}`, 'PATCH', payload),
  retirePlan: (id: number) => send<{ message: string }>(`/api/v1/plans/${id}`, 'DELETE'),
  offerPlan: (id: number, payload: Record<string, unknown>) => send<{ data: Plan }>(`/api/v1/plans/${id}/offer`, 'POST', payload),
  restorePlanPrice: (id: number) => send<{ data: Plan }>(`/api/v1/plans/${id}/restore-price`, 'POST'),
  batches: () => get<Paginated<Batch>>('/api/v1/voucher-batches'),
  createBatch: (payload: Record<string, unknown>) => send<{ data: Batch }>('/api/v1/voucher-batches', 'POST', payload),
  updateBatch: (id: number, payload: Record<string, unknown>) => send<{ data: Batch }>(`/api/v1/voucher-batches/${id}`, 'PATCH', payload),
  disableBatch: (id: number, reason: string) =>
    send<{ message: string }>(`/api/v1/voucher-batches/${id}/disable`, 'POST', { reason }),
  sites: () => get<{ data: Site[] }>('/api/v1/sites'),
  site: (id: number) =>
    get<{
      data: Site;
      remaining_cards: number;
      online_count: number;
      today: { lipia_minor: number; kadi_minor: number; total_minor: number };
      router_quiet: boolean;
    }>(`/api/v1/sites/${id}`),
  createSite: (payload: Record<string, unknown>) => send<{ data: Site }>('/api/v1/sites', 'POST', payload),
  updateSite: (id: number, payload: Record<string, unknown>) => send<{ data: Site }>(`/api/v1/sites/${id}`, 'PATCH', payload),
  nasDevices: () => get<{ data: NasDevice[] }>('/api/v1/nas-devices'),
  createNas: (payload: Record<string, unknown>) => send<{ data: NasDevice }>('/api/v1/nas-devices', 'POST', payload),
  updateNas: (id: number, payload: Record<string, unknown>) => send<{ data: NasDevice }>(`/api/v1/nas-devices/${id}`, 'PATCH', payload),
  snippet: (id: number) => get<{ rsc: string; login_html: string }>(`/api/v1/nas-devices/${id}/snippet`),
  devices: () => get<Paginated<Device>>('/api/v1/devices'),
  revokeDevice: (id: number) => send<{ message: string }>(`/api/v1/devices/${id}`, 'DELETE'),
  agents: () => get<{ data: User[] }>('/api/v1/agents'),
  createAgent: (payload: Record<string, unknown>) =>
    send<{ data: User; password: string }>('/api/v1/agents', 'POST', payload),
  updateAgent: (id: number, payload: Record<string, unknown>) => send<{ data: User }>(`/api/v1/agents/${id}`, 'PATCH', payload),
  settings: () => get<{ data: Tenant }>('/api/v1/settings'),
  updateSettings: (payload: Record<string, unknown>) => send<{ data: Tenant }>('/api/v1/settings', 'PATCH', payload),
  uploadLogo: async (file: File) => {
    const body = new FormData();
    body.append('logo', file);
    const res = await fetch('/api/v1/settings/logo', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
      },
      body,
    });
    return parse<{ data: Tenant }>(res);
  },
  customers: (status?: string, siteId?: number, q?: string, page = 1) => {
    const params = new URLSearchParams();
    if (status && status !== 'all') params.set('status', status);
    if (siteId) params.set('site_id', String(siteId));
    if (q) params.set('q', q);
    params.set('per_page', '10');
    params.set('page', String(page));
    return get<Paginated<Customer>>(`/api/v1/customers?${params}`);
  },
  customer: (id: number) => get<{ data: Customer; unused_voucher: { id: number; plan: string | null } | null }>(`/api/v1/customers/${id}`),
  revealCustomerVoucher: (id: number) => send<{ code: string }>(`/api/v1/customers/${id}/reveal`, 'POST'),
  collections: (period: string, siteId?: number, agentId?: number) => {
    const params = new URLSearchParams({ period });
    if (siteId) params.set('site_id', String(siteId));
    if (agentId) params.set('agent_id', String(agentId));
    return get<Collections>(`/api/v1/reports/collections?${params}`);
  },
  insights: (period: string) => get<Insights>(`/api/v1/reports/insights?period=${period}`),
  agentDesk: (siteId?: number) => get<AgentDesk>(`/api/v1/agent/desk${siteId ? `?site_id=${siteId}` : ''}`),
  platformOverview: () => get<PlatformOverview>('/api/v1/platform/overview'),
  campaigns: () => get<{ data: Campaign[] }>('/api/v1/campaigns'),
  createCampaign: (payload: Record<string, unknown>) => send<{ data: Campaign }>('/api/v1/campaigns', 'POST', payload),
  invoices: () => get<Paginated<PlatformInvoice>>('/api/v1/billing/invoices'),
  billingSummary: () => get<{ data: BillingSummary }>('/api/v1/billing/summary'),
  platformPaymentDetails: () => get<{ data: PlatformPaymentDetails }>('/api/v1/platform/payment-details'),
  updatePlatformPaymentDetails: (payload: PlatformPaymentDetails) =>
    send<{ data: PlatformPaymentDetails }>('/api/v1/platform/payment-details', 'PATCH', payload),
  platformTenants: () => get<{ data: Tenant[] }>('/api/v1/platform/tenants'),
  createPlatformTenant: (payload: Record<string, unknown>) =>
    send<{ data: Tenant; admin: User; password: string }>('/api/v1/platform/tenants', 'POST', payload),
  updatePlatformTenant: (uuid: string, payload: Record<string, unknown>) =>
    send<{ data: Tenant }>(`/api/v1/platform/tenants/${uuid}`, 'PATCH', payload),
  platformInvoices: () => get<Paginated<PlatformInvoice>>('/api/v1/platform/invoices'),
  createPlatformInvoice: (payload: Record<string, unknown>) =>
    send<{ data: PlatformInvoice }>('/api/v1/platform/invoices', 'POST', payload),
  markInvoicePaid: (id: number) => send<{ data: PlatformInvoice }>(`/api/v1/platform/invoices/${id}/paid`, 'POST'),
  revenue: () => get<{ data: { day: string; orders: number; total: number }[]; from: string; to: string }>('/api/v1/reports/revenue'),
  orders: () => get<Paginated<Order>>('/api/v1/reports/orders'),
};

export async function openPrintSheet(batchId: number, count?: number): Promise<void> {
  const params = new URLSearchParams();
  if (count && count > 0) {
    params.set('from', '1');
    params.set('to', String(count));
  }
  const query = params.toString();
  const res = await fetch(`/api/print/voucher-batches/${batchId}${query ? `?${query}` : ''}`, {
    headers: {
      Accept: 'application/json, text/html',
      ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
    },
  });

  if (!res.ok) {
    const contentType = res.headers.get('content-type') ?? '';
    let message = 'Could not open the print sheet.';
    if (contentType.includes('json')) {
      const body = (await res.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
      message = body.message || body.errors?.batch?.[0] || message;
    } else {
      const text = await res.text().catch(() => '');
      if (text.includes('no unprinted')) {
        message = 'There are no unprinted vouchers left in this batch. Each code can only be printed once.';
      }
    }
    throw new Error(message);
  }

  const html = await res.text();
  const url = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
  window.open(url, '_blank', 'noopener');
}
