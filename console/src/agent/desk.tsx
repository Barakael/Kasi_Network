import { useQuery } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { api, type AgentDesk } from '../api';

const SITE_KEY = 'kasi.agent.site';

type AgentDeskValue = {
  siteId: number | undefined;
  setSiteId: (id: number) => void;
  desk: AgentDesk | undefined;
  isLoading: boolean;
  isError: boolean;
  error: Error | null;
};

const AgentDeskContext = createContext<AgentDeskValue | null>(null);

function storedSiteId(): number | undefined {
  const raw = sessionStorage.getItem(SITE_KEY);
  const id = raw ? Number(raw) : NaN;
  return Number.isFinite(id) && id > 0 ? id : undefined;
}

export function AgentDeskProvider({ children }: { children: ReactNode }) {
  const [siteId, setSiteIdState] = useState<number | undefined>(storedSiteId);

  const setSiteId = useCallback((id: number) => {
    sessionStorage.setItem(SITE_KEY, String(id));
    setSiteIdState(id);
  }, []);

  const query = useQuery({
    queryKey: ['desk', siteId ?? 'default'],
    queryFn: () => api.agentDesk(siteId),
    refetchInterval: 15_000,
  });

  const value = useMemo<AgentDeskValue>(
    () => ({
      siteId: query.data?.site.id ?? siteId,
      setSiteId,
      desk: query.data,
      isLoading: query.isLoading,
      isError: query.isError,
      error: (query.error as Error) ?? null,
    }),
    [query.data, query.isLoading, query.isError, query.error, setSiteId, siteId],
  );

  return <AgentDeskContext.Provider value={value}>{children}</AgentDeskContext.Provider>;
}

export function useAgentDesk(): AgentDeskValue {
  const ctx = useContext(AgentDeskContext);
  if (!ctx) {
    throw new Error('useAgentDesk must be used inside AgentDeskProvider');
  }
  return ctx;
}
