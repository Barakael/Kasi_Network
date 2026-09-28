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

type JsQr = typeof import('jsqr').default;

let detector: Detector | null | undefined;
let jsQrLoader: Promise<JsQr> | null = null;

function nativeDetector(): Detector | null {
  if (detector !== undefined) {
    return detector;
  }
  const Ctor = (window as unknown as { BarcodeDetector?: new (opts?: { formats: string[] }) => Detector })
    .BarcodeDetector;
  if (!Ctor) {
    detector = null;
    return null;
  }
  try {
    detector = new Ctor({ formats: ['qr_code'] });
  } catch {
    try {
      detector = new Ctor();
    } catch {
      detector = null;
    }
  }
  return detector;
}

async function jsQr(): Promise<JsQr> {
  jsQrLoader ??= import('jsqr').then((mod) => mod.default);
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

async function decodeNative(source: ImageBitmapSource): Promise<string | null> {
  const native = nativeDetector();
  if (!native) {
    return null;
  }
  try {
    const codes = await native.detect(source);
    for (const item of codes) {
      const code = item.rawValue ? extractVoucherCode(item.rawValue) : null;
      if (code) {
        return code;
      }
    }
  } catch {
    // Fall through to jsQR on browsers whose detector exists but fails.
  }
  return null;
}

async function decodePixels(canvas: HTMLCanvasElement, ctx: CanvasRenderingContext2D): Promise<string | null> {
  const fromNative = await decodeNative(canvas);
  if (fromNative) {
    return fromNative;
  }

  if (canvas.width < 32 || canvas.height < 32) {
    return null;
  }

  const decode = await jsQr();
  const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const result = decode(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });
  return result?.data ? extractVoucherCode(result.data) : null;
}

async function decodeDrawn(
  draw: (ctx: CanvasRenderingContext2D, width: number, height: number) => void,
  width: number,
  height: number,
): Promise<string | null> {
  const frame = canvasFromDraw(draw, width, height);
  if (!frame) {
    return null;
  }
  return decodePixels(frame.canvas, frame.ctx);
}

export async function decodeQrFromVideo(video: HTMLVideoElement): Promise<string | null> {
  if (video.readyState < 2 || video.videoWidth < 16) {
    return null;
  }

  const fromNative = await decodeNative(video);
  if (fromNative) {
    return fromNative;
  }

  const vw = video.videoWidth;
  const vh = video.videoHeight;
  const scale = Math.min(1, 720 / Math.max(vw, vh));
  const fromFull = await decodeDrawn(
    (ctx, width, height) => {
      ctx.drawImage(video, 0, 0, width, height);
    },
    vw * scale,
    vh * scale,
  );
  if (fromFull) {
    return fromFull;
  }

  const side = Math.min(vw, vh) * 0.72;
  return decodeDrawn(
    (ctx) => {
      ctx.drawImage(video, (vw - side) / 2, (vh - side) / 2, side, side, 0, 0, 480, 480);
    },
    480,
    480,
  );
}

type DecodedImage = CanvasImageSource & { width: number; height: number; close?: () => void };

async function imageFromObjectUrl(file: File): Promise<DecodedImage> {
  const url = URL.createObjectURL(file);
  const image = new Image();
  image.src = url;
  try {
    await image.decode();
  } catch {
    URL.revokeObjectURL(url);
    throw new Error('Could not read that photo');
  }

  return Object.assign(image, {
    close: () => URL.revokeObjectURL(url),
  });
}

async function bitmapFromFile(file: File): Promise<DecodedImage> {
  try {
    return await createImageBitmap(file, { imageOrientation: 'from-image' });
  } catch {
    try {
      return await createImageBitmap(file);
    } catch {
      return imageFromObjectUrl(file);
    }
  }
}

export async function decodeQrFromFile(file: File): Promise<string | null> {
  const source = await bitmapFromFile(file);
  try {
    const fromNative = await decodeNative(source);
    if (fromNative) {
      return fromNative;
    }

    const width = source.width;
    const height = source.height;
    const longest = Math.max(width, height);
    const sizes = [Math.min(1400, longest), 900, 640];

    for (const target of sizes) {
      const scale = Math.min(1, target / longest);
      const code = await decodeDrawn(
        (ctx, w, h) => {
          ctx.drawImage(source, 0, 0, w, h);
        },
        width * scale,
        height * scale,
      );
      if (code) {
        return code;
      }
    }

    const side = Math.min(width, height) * 0.75;
    return decodeDrawn(
      (ctx) => {
        ctx.drawImage(source, (width - side) / 2, (height - side) / 2, side, side, 0, 0, 640, 640);
      },
      640,
      640,
    );
  } finally {
    source.close?.();
  }
}
