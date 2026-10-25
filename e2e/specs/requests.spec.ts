/**
 * 案件の登録・編集・削除（業務の主な流れ）。
 *
 * ここで確かめるのは、Feature テスト（PHPUnit）では動かない画面の JavaScript：
 * - 地図をクリックすると位置が入力欄に入り、ピンが立つ／「位置をクリア」で消える
 * - 受付方法「その他」で自由入力欄が出る
 * - 一覧の行をクリックすると詳細へ移る
 * - 編集画面で「対応完了」を選ぶと完了日が入力できるようになる
 * - 削除は確認ダイアログで「キャンセル」すると何も起きない
 * 入力チェックの細かな境界値などは Feature テストで確かめ済みのため、ここではやらない。
 */

import type { Page } from '@playwright/test';
import { expect, login, test } from '../fixtures/test.ts';
import { USERS } from '../fixtures/data.ts';

/** 新規登録画面で必須項目だけ入れて登録し、一覧に出た受付番号を返す。 */
async function registerRequest(page: Page, content: string): Promise<string> {
  await page.locator('textarea[name="content"]').fill(content);
  await page.getByRole('button', { name: '登録する' }).click();

  await expect(page).toHaveURL(/\/requests$/);
  const flash = page.locator('.alert-success');
  await expect(flash).toContainText('で登録しました');
  const number = (await flash.textContent())?.match(/受付番号 (\S+) で登録しました/)?.[1];
  expect(number, '登録完了のメッセージに受付番号が無い').toBeTruthy();
  return number!;
}

test('案件を登録すると、地図で選んだ位置が詳細画面に出る', async ({ page, tileRequests }) => {
  const content = `【E2E】登録テスト ${Date.now()}`;

  await login(page, USERS.road);
  await page.getByRole('link', { name: '新規登録' }).click();
  await expect(page.getByRole('heading', { name: '新規登録' })).toBeVisible();

  // 受付方法「その他」で自由入力欄が出て、ほかに戻すと消える。
  const methodOther = page.locator('input[name="reception_method_other"]');
  await expect(methodOther).toBeHidden();
  await page.locator('select[name="reception_method"]').selectOption('other');
  await expect(methodOther).toBeVisible();
  await page.locator('select[name="reception_method"]').selectOption('phone');
  await expect(methodOther).toBeHidden();

  // 地図：最初は位置が空で、ピンも「位置をクリア」も無い。
  const map = page.locator('#create-map');
  const lat = page.locator('#create-map-lat');
  const lng = page.locator('#create-map-lng');
  const clear = page.getByRole('button', { name: '位置をクリア' });
  await expect(map).toBeVisible();
  await expect(lat).toHaveValue('');
  await expect(clear).toBeHidden();
  expect(tileRequests.count, '地図が背景のタイルを読み込もうとしていない').toBeGreaterThan(0);

  // 地図をクリックすると、緯度・経度（小数6桁）が入り、ピンが立つ。
  await map.click({ position: { x: 200, y: 150 } });
  await expect(lat).toHaveValue(/^-?\d+\.\d{6}$/);
  await expect(lng).toHaveValue(/^-?\d+\.\d{6}$/);
  await expect(map.locator('.leaflet-marker-icon')).toHaveCount(1);
  await expect(clear).toBeVisible();

  // 「位置をクリア」で、位置とピンが消える。
  await clear.click();
  await expect(lat).toHaveValue('');
  await expect(lng).toHaveValue('');
  await expect(map.locator('.leaflet-marker-icon')).toHaveCount(0);

  // もう一度選んで登録する。
  await map.click({ position: { x: 300, y: 120 } });
  const savedLat = await lat.inputValue();
  const savedLng = await lng.inputValue();
  expect(savedLat).not.toBe('');
  const number = await registerRequest(page, content);

  // 一覧の行をクリックすると詳細へ移る。
  await page.getByRole('row').filter({ hasText: number }).click();
  await expect(page.getByRole('heading', { name: `案件詳細（受付番号：${number}）` })).toBeVisible();
  await expect(page.getByText(content)).toBeVisible();

  // 選んだ位置が保存され、詳細の地図にピンが1本出る。
  // 表示は DB の小数6桁のまま（例：34.812345）なので、数値として比べる。
  const coords = await page.getByText(/緯度: .+, 経度: .+/).textContent();
  const [, shownLat, shownLng] = coords!.match(/緯度: ([\d.-]+), 経度: ([\d.-]+)/)!;
  expect(Number(shownLat)).toBeCloseTo(Number(savedLat), 6);
  expect(Number(shownLng)).toBeCloseTo(Number(savedLng), 6);
  await expect(page.locator('#detail-map .leaflet-marker-icon')).toHaveCount(1);
});

test('編集画面で対応完了にすると完了日が入力でき、削除は確認してから行われる', async ({ page }) => {
  const content = `【E2E】削除テスト ${Date.now()}`;

  await login(page, USERS.road);
  await page.goto('/requests/create');
  const number = await registerRequest(page, content);
  await page.getByRole('row').filter({ hasText: number }).click();
  await page.getByRole('link', { name: '編集する' }).click();

  // 対応状況が「対応完了」のときだけ、完了日を入力できる。
  const status = page.locator('select[name="response_status"]');
  const completedDate = page.locator('#response_completed_date');
  await expect(completedDate).toBeDisabled();
  await status.selectOption('completed');
  await expect(completedDate).toBeEnabled();
  await status.selectOption('in_progress');
  await expect(completedDate).toBeDisabled();

  // 確認ダイアログで「キャンセル」すると、削除されずに編集画面に残る。
  const deleteButton = page.getByRole('button', { name: 'この案件を削除する' });
  page.once('dialog', (dialog) => {
    expect(dialog.message()).toContain('この案件を削除します');
    return dialog.dismiss();
  });
  await deleteButton.click();
  await expect(page).toHaveURL(/\/requests\/\d+\/edit$/);

  // 「OK」すると削除され、一覧から消える。
  page.once('dialog', (dialog) => dialog.accept());
  await deleteButton.click();
  await expect(page).toHaveURL(/\/requests$/);
  await expect(page.locator('.alert-success')).toBeVisible();
  await expect(page.getByRole('row').filter({ hasText: number })).toHaveCount(0);
});
