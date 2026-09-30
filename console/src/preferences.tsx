import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type Locale = 'sw' | 'en';
export type ThemeName = 'light' | 'dark';

type Prefs = {
  locale: Locale;
  theme: ThemeName;
  setLocale: (locale: Locale) => void;
  setTheme: (theme: ThemeName) => void;
  sw: (kiswahili: string, english: string) => string;
};

const PrefsContext = createContext<Prefs | null>(null);

function storedLocale(): Locale {
  return localStorage.getItem('kasi.locale') === 'en' ? 'en' : 'sw';
}

function storedTheme(): ThemeName {
  return localStorage.getItem('kasi.theme') === 'dark' ? 'dark' : 'light';
}

export function PreferencesProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(storedLocale);
  const [theme, setThemeState] = useState<ThemeName>(storedTheme);

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.lang = locale === 'sw' ? 'sw' : 'en';
    localStorage.setItem('kasi.theme', theme);
    localStorage.setItem('kasi.locale', locale);
    const notch = theme === 'dark' ? '#121820' : '#ffffff';
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', notch);
    document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', theme);
  }, [locale, theme]);

  const sw = useCallback((kiswahili: string, english: string) => (locale === 'en' ? english : kiswahili), [locale]);

  const value = useMemo<Prefs>(
    () => ({
      locale,
      theme,
      setLocale: setLocaleState,
      setTheme: setThemeState,
      sw,
    }),
    [locale, theme, sw],
  );

  return <PrefsContext.Provider value={value}>{children}</PrefsContext.Provider>;
}

export function usePrefs(): Prefs {
  const ctx = useContext(PrefsContext);
  if (!ctx) {
    throw new Error('usePrefs must be used inside PreferencesProvider');
  }
  return ctx;
}
