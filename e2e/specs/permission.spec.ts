/**
 * 権限による画面の出し分け（軽い確認）。
 *
 * 編集・削除の可否そのもの（直接 URL を叩いても弾かれるか等）は Feature テストで確かめ済み。
 * ここでは「利用者から見て、できない操作のボタンが出ていないか」だけを確かめる。
 */

import { expect, login, test } from '../fixtures/test.ts';
import { REQUESTS, USERS } from '../fixtures/data.ts';

test('他部署の案件は閲覧のみで、管理メニューは管理者にしか出ない', async ({ page }) => {
  // みどり土木事務所の河川担当が、同じ事務所の道路の案件を開く。
  await login(page, USERS.river);
  await expect(page.getByRole('link', { name: 'ユーザー管理' })).toHaveCount(0);

  await page.getByRole('link', { name: '案件一覧', exact: true }).click();
  await page.getByRole('row').filter({ hasText: REQUESTS.midoriRoad.slice(0, 20) }).click();
  await expect(page.getByText(REQUESTS.midoriRoad)).toBeVisible();

  await expect(page.getByText('対応部署が異なるため、案件情報の編集・削除はできません')).toBeVisible();
  await expect(page.getByRole('link', { name: '編集する' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: '履歴を追加する' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: '写真をアップロードする' })).toHaveCount(0);
});
