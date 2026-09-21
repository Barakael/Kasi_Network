/**
 * Voucher QR payloads are portal deep-links (`…/r/{CODE}`), not bare codes.
 * Phone-camera apps open that URL; the in-portal scanner fills the input instead.
 */
export function extractVoucherCode(raw: string): string | null {
  const trimmed = raw.trim();
  if (!trimmed) {
    return null;
  }

  try {
    const url = new URL(trimmed);
    const match = url.pathname.match(/\/r\/([A-Z0-9-]+)/i);
    if (match?.[1]) {
      return match[1].toUpperCase();
    }
  } catch {
    // Not a URL — fall through and treat as a typed code.
  }

  const code = trimmed.replace(/\s+/g, '').toUpperCase();
  if (/^[A-Z0-9-]{6,32}$/.test(code)) {
    return code;
  }

  return null;
}

type Detector = {
  detect: (source: ImageBitmapSource) => Promise<Array<{ rawValue: string }>>;
};

export function createQrDetector(): Detector | null {
  const Ctor = (window as unknown as { BarcodeDetector?: new (opts?: { formats: string[] }) => Detector })
    .BarcodeDetector;
  if (!Ctor) {
    return null;
  }
  try {
    return new Ctor({ formats: ['qr_code'] });
  } catch {
    return null;
  }
}
