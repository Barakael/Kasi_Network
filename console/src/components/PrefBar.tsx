import { NavLink } from 'react-router';
import { usePrefs } from '../preferences';

function SunIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2.5v2.2M12 19.3v2.2M4.8 4.8l1.6 1.6M17.6 17.6l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.8 19.2l1.6-1.6M17.6 6.4l1.6-1.6" />
    </svg>
  );
}

function MoonIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <path d="M20.5 14.2A8.2 8.2 0 1 1 9.8 3.5a6.6 6.6 0 0 0 10.7 10.7Z" />
    </svg>
  );
}

function GearIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <circle cx="12" cy="12" r="3" />
      <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />
    </svg>
  );
}

function Flag({ which }: { which: 'us' | 'tz' }) {
  if (which === 'us') {
    return (
      <svg className="h-4 w-6 overflow-hidden rounded-[3px]" viewBox="0 0 19 10" aria-hidden>
        <rect width="19" height="10" fill="#b22234" />
        <path stroke="#fff" strokeWidth="1" d="M0 1.5h19M0 3.5h19M0 5.5h19M0 7.5h19M0 9.5h19" />
        <rect width="8" height="5.5" fill="#3c3b6e" />
      </svg>
    );
  }

  return (
    <svg className="h-4 w-6 overflow-hidden rounded-[3px]" viewBox="0 0 30 20" aria-hidden>
      <rect width="30" height="20" fill="#1eb53a" />
      <polygon points="30,0 30,20 0,20" fill="#00a3dd" />
      <line x1="0" y1="20" x2="30" y2="0" stroke="#fcd116" strokeWidth="7" />
      <line x1="0" y1="20" x2="30" y2="0" stroke="#000" strokeWidth="3.4" />
    </svg>
  );
}

export function PrefBar({ showSettings = false, compact = false }: { showSettings?: boolean; compact?: boolean }) {
  const { sw, theme, setTheme, locale, setLocale } = usePrefs();
  const toEnglish = locale === 'sw';
  const darkNow = theme === 'dark';
  const chip = compact ? 'grid h-9 w-9 shrink-0 place-items-center rounded-lg text-ink-800' : 'pref-chip grid flex-1 place-items-center';

  return (
    <div className={compact ? 'flex shrink-0 items-center gap-1' : 'flex items-center gap-1 rounded-xl bg-slate-50 p-1'}>
      <button
        type="button"
        className={chip}
        aria-label={darkNow ? sw('Mchana', 'Day') : sw('Usiku', 'Night')}
        onClick={() => setTheme(darkNow ? 'light' : 'dark')}
      >
        {darkNow ? <SunIcon /> : <MoonIcon />}
      </button>
      <button
        type="button"
        className={chip}
        aria-label={toEnglish ? 'English' : 'Kiswahili'}
        onClick={() => setLocale(toEnglish ? 'en' : 'sw')}
      >
        <Flag which={toEnglish ? 'us' : 'tz'} />
      </button>
      {showSettings && (
        <NavLink
          to="/settings"
          aria-label={sw('Mipangilio', 'Settings')}
          className={({ isActive }) => `pref-chip grid flex-1 place-items-center ${isActive ? 'pref-chip-on' : ''}`}
        >
          <GearIcon />
        </NavLink>
      )}
    </div>
  );
}
