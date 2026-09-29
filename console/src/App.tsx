import { Navigate, Route, Routes } from 'react-router';
import { isAgent, isPlatformAdmin, useAuth } from './auth';
import { Layout } from './components/Layout';
import { AccountPage } from './pages/Account';
import { AgentAnalyticsPage } from './pages/AgentAnalytics';
import { AgentDeskPage } from './pages/AgentDesk';
import { AgentPrintPage } from './pages/AgentPrint';
import { AgentOnlinePage } from './pages/AgentOnline';
import { AgentCustomersPage } from './pages/AgentCustomers';
import { AgentAccountPage } from './pages/AgentAccount';
import { AgentsPage } from './pages/Agents';
import { OperatorAnalyticsPage } from './pages/OperatorAnalytics';
import { PlatformOverviewPage } from './pages/PlatformOverview';
import { BatchesPage } from './pages/Batches';
import { BillingPage } from './pages/Billing';
import { CampaignsPage } from './pages/Campaigns';
import { CollectionsPage } from './pages/Collections';
import { CustomersPage } from './pages/Customers';
import { DashboardPage } from './pages/Dashboard';
import { DevicesPage } from './pages/Devices';
import { LoginPage } from './pages/Login';
import { PlansPage } from './pages/Plans';
import { PlatformInvoicesPage } from './pages/PlatformInvoices';
import { PlatformPage } from './pages/Platform';
import { RoutersPage } from './pages/Routers';
import { SessionsPage } from './pages/Sessions';
import { SettingsPage } from './pages/Settings';
import { SitesPage } from './pages/Sites';
import type { ReactNode } from 'react';

function Splash() {
  return (
    <main className="flex min-h-dvh items-center justify-center bg-slate-50 text-ink-700">
      Loading console…
    </main>
  );
}

function Guard({
  children,
  agents,
  platform,
}: {
  children: ReactNode;
  agents?: 'only' | 'forbid';
  platform?: 'only' | 'forbid';
}) {
  const { user, ready } = useAuth();

  if (!ready) {
    return <Splash />;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (agents === 'only' && !isAgent(user)) {
    return <Navigate to={isPlatformAdmin(user) ? '/platform' : '/'} replace />;
  }

  if (agents === 'forbid' && isAgent(user)) {
    return <Navigate to="/desk" replace />;
  }

  if (platform === 'only' && !isPlatformAdmin(user)) {
    return <Navigate to={isAgent(user) ? '/desk' : '/'} replace />;
  }

  if (platform === 'forbid' && isPlatformAdmin(user)) {
    return <Navigate to="/platform" replace />;
  }

  return children;
}

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        element={
          <Guard>
            <Layout />
          </Guard>
        }
      >
        <Route
          index
          element={
            <Guard agents="forbid" platform="forbid">
              <DashboardPage />
            </Guard>
          }
        />
        <Route
          path="customers"
          element={
            <Guard agents="forbid" platform="forbid">
              <CustomersPage />
            </Guard>
          }
        />
        <Route
          path="sessions"
          element={
            <Guard agents="forbid" platform="forbid">
              <SessionsPage />
            </Guard>
          }
        />
        <Route
          path="plans"
          element={
            <Guard agents="forbid" platform="forbid">
              <PlansPage />
            </Guard>
          }
        />
        <Route
          path="batches"
          element={
            <Guard agents="forbid" platform="forbid">
              <BatchesPage />
            </Guard>
          }
        />
        <Route
          path="agents"
          element={
            <Guard agents="forbid" platform="forbid">
              <AgentsPage />
            </Guard>
          }
        />
        <Route
          path="sites"
          element={
            <Guard agents="forbid" platform="forbid">
              <SitesPage />
            </Guard>
          }
        />
        <Route
          path="routers"
          element={
            <Guard agents="forbid" platform="forbid">
              <RoutersPage />
            </Guard>
          }
        />
        <Route
          path="analytics"
          element={
            <Guard agents="forbid" platform="forbid">
              <OperatorAnalyticsPage />
            </Guard>
          }
        />
        <Route
          path="collections"
          element={
            <Guard agents="forbid" platform="forbid">
              <CollectionsPage />
            </Guard>
          }
        />
        <Route
          path="devices"
          element={
            <Guard agents="forbid" platform="forbid">
              <DevicesPage />
            </Guard>
          }
        />
        <Route
          path="billing"
          element={
            <Guard agents="forbid" platform="forbid">
              <BillingPage />
            </Guard>
          }
        />
        <Route
          path="account"
          element={
            <Guard agents="forbid">
              <AccountPage />
            </Guard>
          }
        />
        <Route
          path="campaigns"
          element={
            <Guard agents="forbid" platform="forbid">
              <CampaignsPage />
            </Guard>
          }
        />
        <Route
          path="settings"
          element={
            <Guard agents="forbid" platform="forbid">
              <SettingsPage />
            </Guard>
          }
        />
        <Route
          path="desk"
          element={
            <Guard agents="only">
              <AgentDeskPage />
            </Guard>
          }
        />
        <Route
          path="desk/analytics"
          element={
            <Guard agents="only">
              <AgentAnalyticsPage />
            </Guard>
          }
        />
        <Route
          path="desk/uza"
          element={
            <Guard agents="only">
              <AgentPrintPage />
            </Guard>
          }
        />
        <Route
          path="desk/chapisha"
          element={
            <Guard agents="only">
              <AgentPrintPage />
            </Guard>
          }
        />
        <Route
          path="desk/online"
          element={
            <Guard agents="only">
              <AgentOnlinePage />
            </Guard>
          }
        />
        <Route
          path="desk/wateja"
          element={
            <Guard agents="only">
              <AgentCustomersPage />
            </Guard>
          }
        />
        <Route
          path="desk/akaunti"
          element={
            <Guard agents="only">
              <AgentAccountPage />
            </Guard>
          }
        />
        <Route
          path="platform"
          element={
            <Guard platform="only">
              <PlatformOverviewPage />
            </Guard>
          }
        />
        <Route
          path="platform/operators"
          element={
            <Guard platform="only">
              <PlatformPage />
            </Guard>
          }
        />
        <Route
          path="platform/invoices"
          element={
            <Guard platform="only">
              <PlatformInvoicesPage />
            </Guard>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
