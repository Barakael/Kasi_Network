import { useNavigate } from 'react-router';
import { isAgent, useAuth } from '../auth';
import { AgentLayout } from './AgentLayout';
import { OperatorLayout } from './OperatorLayout';

export function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function onLogout() {
    await logout();
    navigate('/login', { replace: true });
  }

  if (isAgent(user)) {
    return <AgentLayout onLogout={() => void onLogout()} />;
  }

  return <OperatorLayout />;
}
