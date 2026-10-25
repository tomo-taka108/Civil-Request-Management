/**
 * Playwright の設定（E2E テスト設計書 docs/e2e-test.md）。
 *
 * 対象は e2e/run-e2e.sh が起動する試験専用の環境（http://localhost:8083）。
 * 本番と同じイメージで動いているため、画面・JavaScript・設定は本番と同じものを確かめる。
 */

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  outputDir: './results/test-results',

  // テストは1つずつ順に流す。全テストが同じ DB を使い、案件の登録などでデータが増えるため、
  // 同時に流すと「件数」などの結果が実行のたびに変わりうる（設計書 4章）。
  workers: 1,
  fullyParallel: false,

  // 失敗したら再実行せず、そのまま失敗として見せる。再実行で通ってしまうと、
  // 「たまに落ちる」不安定さに気づけなくなるため。
  retries: 0,

  // 国土地理院の本物のタイルを読み込むテスト（@external）は、ふだんは流さない。
  // E2E_REAL_TILES=1 を付けたときだけ流す（設計書 5章）。
  grepInvert: process.env.E2E_REAL_TILES === '1' ? undefined : /@external/,

  reporter: [
    ['list'],
    ['html', { outputFolder: './results/playwright-report', open: 'never' }],
  ],

  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8083',
    locale: 'ja-JP',
    timezoneId: 'Asia/Tokyo',
    // 失敗したときだけ、画面の写真と操作の記録（トレース）を残す。
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
