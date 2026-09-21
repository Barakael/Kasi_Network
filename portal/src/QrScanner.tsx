import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { createQrDetector, extractVoucherCode } from './voucherQr';

type Props = {
  open: boolean;
  onClose: () => void;
  onCode: (code: string) => void;
};

export function QrScanner({ open, onClose, onCode }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const onCodeRef = useRef(onCode);
  const onCloseRef = useRef(onClose);
  const [hint, setHint] = useState('Point at the voucher QR…');
  const [cameraOk, setCameraOk] = useState(true);

  onCodeRef.current = onCode;
  onCloseRef.current = onClose;

  useEffect(() => {
    if (!open) {
      return;
    }

    let cancelled = false;
    let raf = 0;
    const detector = createQrDetector();

    async function start() {
      if (!detector) {
        setCameraOk(false);
        setHint('This browser cannot read QR live. Use “Take photo” below, or type the code.');
        return;
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: false,
          video: { facingMode: { ideal: 'environment' } },
        });
        if (cancelled) {
          stream.getTracks().forEach((t) => t.stop());
          return;
        }
        streamRef.current = stream;
        const video = videoRef.current;
        if (!video) {
          return;
        }
        video.srcObject = stream;
        await video.play();
        setCameraOk(true);
        setHint('Point at the voucher QR…');

        const tick = async () => {
          if (cancelled || !videoRef.current || videoRef.current.readyState < 2) {
            raf = requestAnimationFrame(() => void tick());
            return;
          }
          try {
            const codes = await detector.detect(videoRef.current);
            const raw = codes[0]?.rawValue;
            if (raw) {
              const code = extractVoucherCode(raw);
              if (code) {
                onCodeRef.current(code);
                onCloseRef.current();
                return;
              }
            }
          } catch {
            // Keep scanning; transient detect errors are common while focusing.
          }
          raf = requestAnimationFrame(() => void tick());
        };
        raf = requestAnimationFrame(() => void tick());
      } catch {
        setCameraOk(false);
        setHint('Camera blocked. Allow camera access, or use “Take photo”.');
      }
    }

    void start();

    return () => {
      cancelled = true;
      cancelAnimationFrame(raf);
      streamRef.current?.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
    };
  }, [open]);

  async function onPhoto(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }

    const detector = createQrDetector();
    if (!detector) {
      setHint('QR reading is not available on this phone. Type the voucher code instead.');
      return;
    }

    try {
      const bitmap = await createImageBitmap(file);
      const codes = await detector.detect(bitmap);
      bitmap.close();
      const code = codes[0]?.rawValue ? extractVoucherCode(codes[0].rawValue) : null;
      if (!code) {
        setHint('No voucher QR found in that photo. Try again closer to the code.');
        return;
      }
      onCode(code);
      onClose();
    } catch {
      setHint('Could not read that photo. Try again or type the code.');
    }
  }

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col bg-leaf-900/92 text-white"
      role="dialog"
      aria-modal="true"
      aria-label="Scan voucher QR"
    >
      <div className="flex items-center justify-between px-4 py-3">
        <p className="text-sm font-medium">Scan voucher</p>
        <button
          type="button"
          onClick={onClose}
          className="min-h-10 rounded-full bg-white/15 px-4 text-sm font-semibold"
        >
          Close
        </button>
      </div>

      <div className="relative mx-4 flex-1 overflow-hidden rounded-3xl bg-black">
        {cameraOk ? (
          <video ref={videoRef} className="h-full w-full object-cover" playsInline muted />
        ) : (
          <div className="flex h-full items-center justify-center px-6 text-center text-sm text-white/80">
            Camera preview unavailable
          </div>
        )}
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div className="h-48 w-48 rounded-3xl border-2 border-white/70 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
        </div>
      </div>

      <div className="space-y-3 px-4 py-5">
        <p className="text-center text-sm text-white/85">{hint}</p>
        <label className="flex min-h-12 cursor-pointer items-center justify-center rounded-2xl bg-leaf-500 font-semibold text-white">
          Take photo of QR
          <input type="file" accept="image/*" capture="environment" className="sr-only" onChange={onPhoto} />
        </label>
      </div>
    </div>
  );
}
