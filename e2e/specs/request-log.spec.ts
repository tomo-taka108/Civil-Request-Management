/**
 * 対応履歴と現場写真。
 *
 * ここで確かめるのは：
 * - 写真つきで履歴を追加すると、写真が実際に画面に表示される
 *   （保存 → 署名付き URL の発行 → その URL で画像を返す、まで本番と同じイメージで通ることを、
 *    画像が読み込めた大きさで確かめる。Feature テストでは画像の表示までは見ていない）
 * - 「編集」ボタンで表示と編集フォームが入れ替わる（画面の JavaScript）
 * - 写真の削除は確認ダイアログを経る
 */

import { expect, login, test } from '../fixtures/test.ts';
import { REQUESTS, USERS } from '../fixtures/data.ts';
import { solidPng } from '../fixtures/png.ts';

test('写真つきで対応履歴を追加でき、写真の表示・削除ができる', async ({ page }) => {
  const logContent = `【E2E】現地で舗装の穴を確認した ${Date.now()}`;
  const photoName = 'e2e-photo.png';

  await login(page, USERS.road);
  await page.getByRole('link', { name: '案件一覧', exact: true }).click();
  await page.getByRole('row').filter({ hasText: REQUESTS.midoriRoad.slice(0, 20) }).click();
  await expect(page.getByText(REQUESTS.midoriRoad)).toBeVisible();

  // 履歴の追加フォームに、内容と写真（64×48 の PNG）を入れて送る。
  const addForm = page.locator('form', { has: page.getByRole('button', { name: '履歴を追加する' }) });
  await addForm.locator('textarea[name="content"]').fill(logContent);
  await addForm.locator('input[name="photos[]"]').setInputFiles({
    name: photoName,
    mimeType: 'image/png',
    buffer: solidPng(64, 48, [70, 130, 180]),
  });
  await addForm.getByRole('button', { name: '履歴を追加する' }).click();

  // 追加した履歴と写真が出て、写真は実際に読み込めている（元の大きさで表示できる）。
  const entry = page.locator('.timeline li').filter({ hasText: logContent });
  await expect(entry).toBeVisible();
  const photo = entry.getByRole('img', { name: `現場写真：${photoName}` });
  await expect(photo).toBeVisible();
  await expect.poll(() => photo.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth)).toBe(64);

  // 「編集」で編集フォームに切り替わり、「キャンセル」で元に戻る。
  const editForm = entry.locator('form[id^="log-edit-"]');
  await expect(editForm).toBeHidden();
  await entry.getByRole('button', { name: '編集' }).click();
  await expect(editForm).toBeVisible();
  await expect(editForm.locator('textarea[name="content"]')).toHaveValue(logContent);
  await editForm.getByRole('button', { name: 'キャンセル' }).click();
  await expect(editForm).toBeHidden();
  await expect(entry.locator('.log-body')).toHaveText(logContent);

  // 写真の削除：確認で「キャンセル」なら残り、「OK」なら消える。
  const deletePhoto = entry.locator('.photo-item').getByRole('button', { name: '削除' });
  page.once('dialog', (dialog) => {
    expect(dialog.message()).toContain('この写真を削除します');
    return dialog.dismiss();
  });
  await deletePhoto.click();
  await expect(photo).toBeVisible();

  page.once('dialog', (dialog) => dialog.accept());
  await deletePhoto.click();
  const entryAfter = page.locator('.timeline li').filter({ hasText: logContent });
  await expect(entryAfter).toBeVisible();
  await expect(entryAfter.getByRole('img')).toHaveCount(0);
});
