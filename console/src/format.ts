export function money(minor: number, currency = 'TZS'): string {
  return `${Number(minor).toLocaleString()} ${currency}`;
}

export function bytes(value: number | null | undefined): string {
  if (value == null) {
    return 'Unlimited';
  }
  if (value >= 1_000_000_000) {
    return `${Number((value / 1_000_000_000).toFixed(2))} GB`;
  }
  return `${Math.round(value / 1_000_000)} MB`;
}

export function duration(seconds: number): string {
  if (seconds >= 86400 && seconds % 86400 === 0) {
    const days = seconds / 86400;
    return `${days} day${days === 1 ? '' : 's'}`;
  }
  if (seconds >= 3600 && seconds % 3600 === 0) {
    const hours = seconds / 3600;
    return `${hours} hour${hours === 1 ? '' : 's'}`;
  }
  return `${Math.max(1, Math.round(seconds / 60))} min`;
}

export function clock(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (h > 0) {
    return `${h}h ${m}m`;
  }
  return `${m}m`;
}
