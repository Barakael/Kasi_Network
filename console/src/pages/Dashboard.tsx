import { useQuery } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Card, PageHeader, primaryBtn } from '../components/ui';
import { money } from '../format';
import { useAuth } from '../auth';

const RevenueChart = lazy(() => import('./RevenueChart'));

export function DashboardPage() {
  const { user } = useAuth();
  const dash = useQuery({ queryKey: ['dashboard'], queryFn: api.dashboard, refetchInterval: 15_000 });
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: api.sessions, refetchInterval: 15_000 });
  const plans = useQuery({ queryKey: ['plans'], queryFn: api.plans });
  const sites = useQuery({ queryKey: ['sites'], queryFn: api.sites });
  const routers = useQuery({ queryKey: ['nas'], queryFn: api.nasDevices });
  const agents = useQuery({ queryKey: ['agents'], queryFn: api.agents });
  const batches = useQuery({ queryKey: ['batches'], queryFn: api.batches });
  const insights = useQuery({ queryKey: ['insights', 'day'], queryFn: () => api.insights('day'), refetchInterval: 60_000 });

  if (dash.isError) {
    return <p className="text-red-700">{(dash.error as Error).message}</p>;
  }

  const data = dash.data;
  const currency = user?.tenant?.currency ?? 'TZS';
  const quiet = data?.router_quiet;
  const lipiaOn = Boolean(user?.tenant?.palmpesa_configured || user?.tenant?.accepts_online_payments);
  const planCount = plans.data?.data.length ?? 0;
  const siteCount = sites.data?.data.length ?? 0;
  const routerCount = routers.data?.data.length ?? 0;
  const agentRows = agents.data?.data ?? [];
  const agentCount = agentRows.length;
  const agentsWithoutSite = agentRows.filter((row) => !(row.sites ?? []).length).length;
  const packRows = batches.data?.data ?? [];
  const unassignedPacks = packRows.filter((row) => row.status !== 'disabled' && !row.assigned_agent).length;

  const actions = nextActions({
    quiet: Boolean(quiet),
    lipiaOn,
    planCount,
    siteCount,
    routerCount,
    agentCount,
    agentsWithoutSite,
    unassignedPacks,
    unusedCards: data?.unused_cards ?? 0,
    plansLoading: plans.isLoading,
    expiringSoon: insights.data?.stock.expiring_soon ?? 0,
    expiringDays: insights.data?.stock.soon_days ?? 7,
    deadStock: insights.data?.stock.dead_stock ?? 0,
  });

  return (
    <div>
      <PageHeader
        title="Live"
        subtitle={`${user?.tenant?.portal_name || user?.tenant?.name || 'Operator'} — radio, Lipia, kadi, na kazi inayofuata.`}
      />
      {actions.length > 0 && (
        <Card className="mb-4">
          <h2 className="mb-3 text-sm font-semibold tracking-wide text-ink-700 uppercase">Kazi inayofuata</h2>
          <ul className="space-y-3">
            {actions.map((action) => (
              <li key={action.to + action.title} className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="font-semibold text-ink-900">{action.title}</p>
                  <p className="text-sm text-ink-700">{action.body}</p>
                </div>
                <Link to={action.to} className={primaryBtn}>
                  {action.cta}
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      )}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Link to="/sessions">
          <Stat label="Mtandaoni" value={String(data?.concurrent_sessions ?? '—')} hint="Kata" />
        </Link>
        <Link to="/collections">
          <Stat label="Leo" value={data ? money(data.revenue_today_minor, currency) : '—'} hint="Lipia + kadi zilizotumika" />
        </Link>
        <Link to="/analytics">
          <Stat label="Wiki hii" value={data ? money(data.week_minor ?? 0, currency) : '—'} />
        </Link>
        <Stat label="Mwezi huu" value={data ? money(data.revenue_month_minor, currency) : '—'} />
      </div>
      <div className="mt-6 grid gap-4 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Siku 14</h2>
            <Link to="/analytics" className="text-sm font-semibold text-brand-700 hover:underline">
              Analytics
            </Link>
          </div>
          <Suspense fallback={<p className="text-sm text-ink-700">Inapakia chati…</p>}>
            <RevenueChart series={data?.revenue_series ?? []} currency={currency} />
          </Suspense>
        </Card>
        <Card className="lg:col-span-2">
          <div className="mb-3 flex items-center justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-wide text-ink-700 uppercase">Mtandaoni sasa</h2>
            <Link to="/sessions" className="text-sm font-semibold text-brand-700 hover:underline">
              Sessions
            </Link>
          </div>
          <ul className="space-y-2 text-sm">
            {(sessions.data?.data ?? []).slice(0, 8).map((session) => (
              <li key={session.acctuniqueid} className="flex justify-between gap-2">
                <span className="font-mono text-xs">{session.callingstationid || session.username.slice(-6)}</span>
                <span className="text-ink-700">{session.framedipaddress}</span>
              </li>
            ))}
            {(sessions.data?.data.length ?? 0) === 0 && <li className="text-ink-700">Hakuna aliye mtandaoni.</li>}
          </ul>
        </Card>
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Link to="/collections">
          <Stat label="Lipia leo" value={data ? money(data.lipia_today_minor ?? 0, currency) : '—'} hint="Portal" />
        </Link>
        <Link to="/collections">
          <Stat label="Kadi leo" value={data ? money(data.kadi_today_minor ?? 0, currency) : '—'} hint="Zilizotumika kwenye Wi‑Fi" />
        </Link>
        <Link to="/batches">
          <Stat label="Kadi hazijauzwa" value={String(data?.unused_cards ?? '—')} hint="Stock" />
        </Link>
        <Link to="/customers">
          <Stat label="Hai" value={String(data?.hai_count ?? '—')} hint={`${data?.kimya_count ?? 0} kimya`} />
        </Link>
      </div>
    </div>
  );
}

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <Card className="h-full transition hover:border-brand-400">
      <p className="text-sm text-ink-700">{label}</p>
      <p className="mt-1 text-2xl font-bold tracking-tight text-ink-900">{value}</p>
      {hint && <p className="mt-1 text-sm text-ink-700">{hint}</p>}
    </Card>
  );
}

function nextActions(state: {
  quiet: boolean;
  lipiaOn: boolean;
  planCount: number;
  siteCount: number;
  routerCount: number;
  agentCount: number;
  agentsWithoutSite: number;
  unassignedPacks: number;
  unusedCards: number;
  plansLoading: boolean;
  expiringSoon: number;
  expiringDays: number;
  deadStock: number;
}): { title: string; body: string; to: string; cta: string }[] {
  const items: { title: string; body: string; to: string; cta: string }[] = [];

  if (state.quiet) {
    items.push({
      title: 'Router kimya',
      body: 'RADIUS haijaona paketi. 0 TZS inaweza kuwa radio, si wateja.',
      to: '/routers',
      cta: 'Routers',
    });
  }
  if (state.siteCount === 0) {
    items.push({
      title: 'Hakuna site',
      body: 'Weka SSID na portal site id kabla ya router au wakala.',
      to: '/sites',
      cta: 'Add site',
    });
  }
  if (state.siteCount > 0 && state.routerCount === 0) {
    items.push({
      title: 'Hakuna router',
      body: 'Ongeza MikroTik na pakua snippet + login.html.',
      to: '/routers',
      cta: 'Add router',
    });
  }
  if (!state.plansLoading && state.planCount === 0) {
    items.push({
      title: 'Hakuna package',
      body: 'Bei za portal na kadi zinatoka hapa. Wateja hawaoni kbps.',
      to: '/plans',
      cta: 'New bundle',
    });
  }
  if (!state.lipiaOn) {
    items.push({
      title: 'Lipia haijawekwa',
      body: 'Bila PalmPesa wateja wananunua kadi tu. Weka token kwenye Settings.',
      to: '/settings',
      cta: 'Settings',
    });
  }
  if (state.agentCount === 0 && state.siteCount > 0) {
    items.push({
      title: 'Hakuna wakala',
      body: 'Wakala anauza kadi kwenye desk. Pin kwa site, kisha mpe pack.',
      to: '/agents',
      cta: 'Invite agent',
    });
  }
  if (state.agentsWithoutSite > 0) {
    items.push({
      title: `${state.agentsWithoutSite} wakala bila site`,
      body: 'Bila site, desk yao hawezi kuuza.',
      to: '/agents',
      cta: 'Fix sites',
    });
  }
  if (state.expiringSoon > 0) {
    items.push({
      title: `${state.expiringSoon} kadi zinaisha`,
      body: `Shelf life inaisha ndani ya siku ${state.expiringDays}. Uza hizi kwanza.`,
      to: '/analytics',
      cta: 'Angalia',
    });
  }
  if (state.deadStock > 0) {
    items.push({
      title: `${state.deadStock} kadi zimekufa`,
      body: 'Hazikuuzwa kabla muda haujaisha. Zitoe kwenye counter.',
      to: '/batches',
      cta: 'Packs',
    });
  }
  if (state.unassignedPacks > 0) {
    items.push({
      title: `${state.unassignedPacks} pack bila wakala`,
      body: 'Stock iliyotengenezwa bado haijapelekwa counter.',
      to: '/batches',
      cta: 'Assign packs',
    });
  }
  if (state.planCount > 0 && state.unusedCards === 0 && state.unassignedPacks === 0 && state.agentCount > 0) {
    items.push({
      title: 'Hakuna kadi za kuuza',
      body: 'Tengeneza pack, chagua site na wakala.',
      to: '/batches',
      cta: 'Create pack',
    });
  }

  return items.slice(0, 3);
}
