import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { decodeQrFromFile, decodeQrFromVideo } from './voucherQr';

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
  const [hint, setHint] = useState('Point at the printed voucher QR…');
  const [cameraOk, setCameraOk] = useState(true);

  onCodeRef.current = onCode;
  onCloseRef.current = onClose;

  useEffect(() => {
    if (!open) {
      return;
    }

    let cancelled = false;
    let timer = 0;

    async function start() {
      if (!navigator.mediaDevices?.getUserMedia) {
        setCameraOk(false);
        setHint('This page cannot open the camera. Use “Take photo of QR”, or type the code.');
        return;
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: false,
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
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
        setHint('Point at the printed voucher QR…');

        const tick = async () => {
          if (cancelled || !videoRef.current) {
            return;
          }
          try {
            const code = await decodeQrFromVideo(videoRef.current);
            if (code) {
              onCodeRef.current(code);
              onCloseRef.current();
              return;
            }
          } catch {
            // Keep scanning while the camera focuses.
          }
          timer = window.setTimeout(() => void tick(), 250);
        };
        timer = window.setTimeout(() => void tick(), 250);
      } catch {
        setCameraOk(false);
        setHint('Camera blocked. Allow camera access, or use “Take photo of QR”.');
      }
    }

    void start();

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
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

    setHint('Reading photo…');
    try {
      const code = await decodeQrFromFile(file);
      if (!code) {
        setHint('No voucher QR found in that photo. Fill the frame with the code and try again.');
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
          <video ref={videoRef} className="h-full w-full object-cover" playsInline autoPlay muted />
        ) : (
          <div className="flex h-full items-center justify-center px-6 text-center text-sm text-white/80">
            {hint}
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
