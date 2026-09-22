#!/usr/bin/env node
// Fails the build if the captive portal's initial payload exceeds its budget.
//
// The portal is fetched over the hotspot link *before* the client has paid, on
// whatever bandwidth the site has left. A regression here is felt by every
// user on every connect, so the budget is enforced rather than monitored.

import { gzipSync } from 'node:zlib';
import { readdir, readFile, stat } from 'node:fs/promises';
import { join, dirname, extname } from 'node:path';
import { fileURLToPath } from 'node:url';

const BUDGET_BYTES = 100 * 1024;
const distDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'dist');

async function collectFiles(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const files = await Promise.all(
    entries.map((entry) => {
      const path = join(dir, entry.name);
      return entry.isDirectory() ? collectFiles(path) : [path];
    }),
  );
  return files.flat();
}

try {
  await stat(distDir);
} catch {
  console.error('portal: dist/ not found. Run `pnpm --filter @kasi/portal build` first.');
  process.exit(1);
}

const files = await collectFiles(distDir);
const rendering = files.filter((file) => {
  if (!['.js', '.css', '.html'].includes(extname(file))) {
    return false;
  }
  // jsQR loads only after the customer taps Scan, so it is not part of the
  // first captive-portal payload every unauthenticated client must download.
  return !/jsqr/i.test(file.replaceAll('\\', '/'));
});

let total = 0;
const rows = [];

for (const file of rendering) {
  const gzipped = gzipSync(await readFile(file)).byteLength;
  total += gzipped;
  rows.push({ file: file.slice(distDir.length + 1), gzipped });
}

rows.sort((a, b) => b.gzipped - a.gzipped);
for (const row of rows) {
  console.log(`  ${(row.gzipped / 1024).toFixed(1).padStart(7)} KB  ${row.file}`);
}

const totalKb = (total / 1024).toFixed(1);
const budgetKb = (BUDGET_BYTES / 1024).toFixed(0);

if (total > BUDGET_BYTES) {
  console.error(`\nportal: initial payload ${totalKb} KB gzipped exceeds the ${budgetKb} KB budget.`);
  process.exit(1);
}

console.log(`\nportal: ${totalKb} KB gzipped, within the ${budgetKb} KB budget.`);
