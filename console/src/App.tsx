import { Navigate, Route, Routes } from 'react-router';
import { isAgent, useAuth } from './auth';
import { Layout } from './components/Layout';
import { AgentPrintPage } from './pages/AgentPrint';
import { BatchesPage } from './pages/Batches';
import { DashboardPage } from './pages/Dashboard';
import { DevicesPage } from './pages/Devices';
import { LoginPage } from './pages/Login';
import { PlansPage } from './pages/Plans';
import { ReportsPage } from './pages/Reports';
import { RoutersPage } from './pages/Routers';
import { SessionsPage } from './pages/Sessions';
import type { ReactNode } from 'react';

function Splash() {
  return (
    <main className="flex min-h-dvh items-center justify-center text-ink-800">
      Loading console…
    </main>
  );
}

function Guard({ children, agents }: { children: ReactNode; agents?: 'only' | 'forbid' }) {
  const { user, ready } = useAuth();

  if (!ready) {
    return <Splash />;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (agents === 'only' && !isAgent(user)) {
    return <Navigate to="/" replace />;
  }

  if (agents === 'forbid' && isAgent(user)) {
    return <Navigate to="/print" replace />;
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
            <Guard agents="forbid">
              <DashboardPage />
            </Guard>
          }
        />
        <Route
          path="sessions"
          element={
            <Guard agents="forbid">
              <SessionsPage />
            </Guard>
          }
        />
        <Route
          path="plans"
          element={
            <Guard agents="forbid">
              <PlansPage />
            </Guard>
          }
        />
        <Route
          path="batches"
          element={
            <Guard agents="forbid">
              <BatchesPage />
            </Guard>
          }
        />
        <Route
          path="routers"
          element={
            <Guard agents="forbid">
              <RoutersPage />
            </Guard>
          }
        />
        <Route
          path="devices"
          element={
            <Guard agents="forbid">
              <DevicesPage />
            </Guard>
          }
        />
        <Route
          path="reports"
          element={
            <Guard agents="forbid">
              <ReportsPage />
            </Guard>
          }
        />
        <Route
          path="print"
          element={
            <Guard agents="only">
              <AgentPrintPage />
            </Guard>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
