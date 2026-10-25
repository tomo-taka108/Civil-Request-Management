/**
 * 国土地理院の本物の地図タイルが読み込めるか（任意で実行。設計書 5章）。
 *
 * ふだんのテストはタイルをダミー画像に差し替えている。このテストだけは本物を読み込み、
 * 「タイルの URL が変わった」「読み込めなくなった」に気づけるようにする。
 * 外のサーバーと回線に左右されるため、ふだんは流さない（playwright.config.ts の grepInvert）。
 *
 *   E2E_REAL_TILES=1 ./e2e/run-e2e.sh
 */

import { expect, login, test } from '../fixtures/test.ts';
import { USERS } from '../fixtures/data.ts';

test.use({ realTiles: true });

test('国土地理院の地図タイルが実際に読み込める @external', async ({ page }) => {
  await login(page, USERS.tsubaki);

  const tile = page.waitForResponse((res) => res.url().startsWith('https://cyberjapandata.gsi.go.jp/xyz/std/'));
  await page.goto('/map');
  const response = await tile;

  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('image/png');
  await expect(page.locator('#map img.leaflet-tile-loaded').first()).toBeVisible();
});
