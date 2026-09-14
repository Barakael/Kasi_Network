import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    // The portal is written against the React API but ships Preact's compat
    // runtime, which costs ~12 KB gzipped instead of ~68 KB. On a hotspot link
    // that every unauthenticated client fetches before paying, that difference
    // dominates time-to-first-paint. The console keeps real React.
    alias: {
      react: 'preact/compat',
      'react-dom': 'preact/compat',
      'react/jsx-runtime': 'preact/jsx-runtime',
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.KASI_API_URL ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    // Budget phones on 2G/3G hotspot links are the target device. es2019 covers
    // Android 6+ stock browsers without pulling in legacy transform bloat.
    target: 'es2019',
    sourcemap: false,
    cssCodeSplit: false,
    reportCompressedSize: true,
    rollupOptions: {
      output: {
        // A captive portal is a single blocking render. Splitting chunks only
        // adds round trips on a link that has not been authorised yet.
        manualChunks: undefined,
      },
    },
  },
});
