/**
 * 地図表示。
 *
 * ピンは画面を開いたあとに JavaScript がサーバーから取り寄せて描くため、
 * Feature テスト（PHPUnit）では「ピンのデータが正しいか」までしか確かめられない。
 * ここでは、そのデータが実際にピンと一覧になって出ること、ピンから詳細へ進めること、
 * 検索条件がピンにも効くことを確かめる。
 *
 * つばき土木事務所の職員で確かめる（ほかのテストはこの事務所に案件を足さないため、
 * ピンの数を言い切れる）。
 */

import { expect, login, test } from '../fixtures/test.ts';
import { REQUESTS, TSUBAKI_PIN_COUNT, USERS } from '../fixtures/data.ts';

test('自分の事務所の位置つき案件がピンで出て、ピンから詳細へ進める', async ({ page, tileRequests }) => {
  await login(page, USERS.tsubaki);
  await page.getByRole('link', { name: '地図表示' }).click();

  // 位置が登録されている案件だけがピンになる（位置なしの1件は出ない）。
  await expect(page.locator('#pin-count')).toHaveText(`検索結果：${TSUBAKI_PIN_COUNT}件をピン表示中`);
  await expect(page.locator('#map .map-pin')).toHaveCount(TSUBAKI_PIN_COUNT);
  await expect(page.locator('#pin-list .map-pin-list-item')).toHaveCount(TSUBAKI_PIN_COUNT);
  expect(tileRequests.count, '地図が背景のタイルを読み込もうとしていない').toBeGreaterThan(0);

  // 横の一覧の項目をクリックすると、地図のピンの吹き出しが開く。
  await page.locator('.map-pin-list-item').filter({ hasText: REQUESTS.tsubakiHigh }).locator('.pin-desc').click();
  const popup = page.locator('.leaflet-popup');
  await expect(popup).toBeVisible();
  await expect(popup).toContainText(REQUESTS.tsubakiHigh);

  // 吹き出しの「詳細を見る」で、その案件の詳細へ進む。
  await popup.getByRole('link', { name: '詳細を見る' }).click();
  await expect(page).toHaveURL(/\/requests\/\d+$/);
  await expect(page.getByText(REQUESTS.tsubakiHigh)).toBeVisible();
});

test('検索条件で絞り込むと、ピンも絞り込まれる', async ({ page }) => {
  await login(page, USERS.tsubaki);
  await page.goto('/map');
  await expect(page.locator('#map .map-pin')).toHaveCount(TSUBAKI_PIN_COUNT);

  // 緊急性「高」だけにすると、ピンは1本になる。
  await page.locator('input[name="urgency[]"][value="high"]').check();
  await page.getByRole('button', { name: '地図に反映' }).click();

  await expect(page).toHaveURL(/urgency%5B%5D=high/);
  await expect(page.locator('#pin-count')).toHaveText('検索結果：1件をピン表示中');
  await expect(page.locator('#map .map-pin')).toHaveCount(1);
  await expect(page.locator('#pin-list')).toContainText(REQUESTS.tsubakiHigh);
  await expect(page.locator('#pin-list')).not.toContainText(REQUESTS.tsubakiLow);
});
