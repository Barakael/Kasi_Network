/**
 * Voucher QR payloads are the normalised code. Older sheets encoded a portal URL;
 * both shapes are accepted so already-printed cards still redeem.
 */
export function extractVoucherCode(raw: string): string | null {
  const trimmed = raw.trim();
  if (!trimmed) {
    return null;
  }

  const fromPath = trimmed.match(/\/r\/([A-Z0-9-]{6,32})/i);
  if (fromPath?.[1]) {
    return fromPath[1].toUpperCase();
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

type JsQrFn = (
  data: Uint8ClampedArray,
  width: number,
  height: number,
  options?: { inversionAttempts?: 'dontInvert' | 'onlyInvert' | 'attemptBoth' | 'invertFirst' },
) => { data: string } | null;

function nativeDetector(): Detector | null {
  const Ctor = (window as unknown as { BarcodeDetector?: new (opts?: { formats: string[] }) => Detector })
    .BarcodeDetector;
  if (!Ctor) {
    return null;
  }
  try {
    return new Ctor({ formats: ['qr_code'] });
  } catch {
    try {
      return new Ctor();
    } catch {
      return null;
    }
  }
}

function unwrapJsQr(mod: Record<string, unknown>): JsQrFn {
  const nested = mod.default as Record<string, unknown> | undefined;
  const candidates = [mod.default, mod, nested?.default];
  for (const candidate of candidates) {
    if (typeof candidate === 'function') {
      return candidate as JsQrFn;
    }
  }
  throw new Error('jsQR failed to load');
}

let jsQrLoader: Promise<JsQrFn> | null = null;

function loadJsQr(): Promise<JsQrFn> {
  jsQrLoader ??= import('jsqr').then((mod) => unwrapJsQr(mod as unknown as Record<string, unknown>));
  return jsQrLoader;
}

function canvasFromDraw(
  draw: (ctx: CanvasRenderingContext2D, width: number, height: number) => void,
  width: number,
  height: number,
): { canvas: HTMLCanvasElement; ctx: CanvasRenderingContext2D } | null {
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(width));
  canvas.height = Math.max(1, Math.round(height));
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  if (!ctx) {
    return null;
  }
  ctx.imageSmoothingEnabled = false;
  draw(ctx, canvas.width, canvas.height);
  return { canvas, ctx };
}

async function decodePixels(canvas: HTMLCanvasElement, ctx: CanvasRenderingContext2D): Promise<string | null> {
  const native = nativeDetector();
  if (native) {
    try {
      const codes = await native.detect(canvas);
      const code = codes[0]?.rawValue ? extractVoucherCode(codes[0].rawValue) : null;
      if (code) {
        return code;
      }
    } catch {
      // Fall through to jsQR on browsers whose detector exists but fails.
    }
  }

  const jsQR = await loadJsQr();
  const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const result = jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });
  return result?.data ? extractVoucherCode(result.data) : null;
}

export async function decodeQrFromVideo(video: HTMLVideoElement): Promise<string | null> {
  if (video.readyState < 2 || video.videoWidth < 16) {
    return null;
  }

  const vw = video.videoWidth;
  const vh = video.videoHeight;
  const side = Math.min(vw, vh) * 0.62;
  const sx = (vw - side) / 2;
  const sy = (vh - side) / 2;
  const out = 512;

  const cropped = canvasFromDraw(
    (ctx) => {
      ctx.drawImage(video, sx, sy, side, side, 0, 0, out, out);
    },
    out,
    out,
  );
  if (cropped) {
    const code = await decodePixels(cropped.canvas, cropped.ctx);
    if (code) {
      return code;
    }
  }

  const scale = Math.min(1, 960 / Math.max(vw, vh));
  const full = canvasFromDraw(
    (ctx, width, height) => {
      ctx.drawImage(video, 0, 0, width, height);
    },
    vw * scale,
    vh * scale,
  );
  if (!full) {
    return null;
  }
  return decodePixels(full.canvas, full.ctx);
}

async function bitmapFromFile(file: File): Promise<CanvasImageSource & { width: number; height: number; close?: () => void }> {
  try {
    return await createImageBitmap(file, { imageOrientation: 'from-image' });
  } catch {
    try {
      return await createImageBitmap(file);
    } catch {
      const url = URL.createObjectURL(file);
      try {
        const image = new Image();
        image.src = url;
        await image.decode();
        return image;
      } finally {
        URL.revokeObjectURL(url);
      }
    }
  }
}

export async function decodeQrFromFile(file: File): Promise<string | null> {
  const source = await bitmapFromFile(file);
  try {
    const width = source.width;
    const height = source.height;
    const scale = Math.min(1, 1000 / Math.max(width, height));
    const full = canvasFromDraw(
      (ctx, w, h) => {
        ctx.drawImage(source, 0, 0, w, h);
      },
      width * scale,
      height * scale,
    );
    if (full) {
      const code = await decodePixels(full.canvas, full.ctx);
      if (code) {
        return code;
      }
    }

    const side = Math.min(width, height) * 0.7;
    const crop = canvasFromDraw(
      (ctx) => {
        ctx.drawImage(source, (width - side) / 2, (height - side) / 2, side, side, 0, 0, 640, 640);
      },
      640,
      640,
    );
    if (!crop) {
      return null;
    }
    return decodePixels(crop.canvas, crop.ctx);
  } finally {
    source.close?.();
  }
}
