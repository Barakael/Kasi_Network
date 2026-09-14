import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, hasToken, setToken, type User } from './api';

type AuthValue = {
  user: User | null;
  ready: boolean;
  login: (email: string, password: string) => Promise<User>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(() => !hasToken());

  useEffect(() => {
    if (!hasToken()) {
      return;
    }

    api
      .me()
      .then(setUser)
      .catch(() => setToken(null))
      .finally(() => setReady(true));
  }, []);

  const value = useMemo<AuthValue>(
    () => ({
      user,
      ready,
      login: async (email, password) => {
        const result = await api.login(email, password);
        setToken(result.token);
        setUser(result.user);
        return result.user;
      },
      logout: async () => {
        await api.logout().catch(() => undefined);
        setToken(null);
        setUser(null);
      },
    }),
    [user, ready],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used inside AuthProvider');
  }
  return ctx;
}

export function isAgent(user: User | null): boolean {
  return user?.role === 'agent';
}
