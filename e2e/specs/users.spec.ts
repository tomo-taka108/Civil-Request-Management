/**
 * ユーザー登録と初回ログイン。
 *
 * 管理者が職員を登録し、その職員が初回ログインでパスワードを変えて使い始めるまでの流れ。
 * あわせて、権限区分を切り替えると所属事務所・担当部署の入力欄が出たり消えたりすること
 * （画面の JavaScript）を確かめる。
 */

import { expect, login, logout, test } from '../fixtures/test.ts';
import { USERS } from '../fixtures/data.ts';

test('管理者が職員を登録し、その職員は初回ログインでパスワードを変えてから使い始める', async ({ page }) => {
  // 実行のたびに環境を作り直すが、念のため ID が重ならないようにする。
  const newUserId = `e2e_new_${Date.now() % 1_000_000}`;
  const initialPassword = 'initial-pass-1';
  const newPassword = 'changed-pass-2';

  await login(page, USERS.admin);
  await page.getByRole('link', { name: 'ユーザー管理' }).click();
  await page.getByRole('link', { name: 'ユーザーを登録する' }).click();

  // 権限区分の切り替えで、職員だけが入力する欄が出たり消えたりする。
  const office = page.locator('select[name="office_id"]');
  const adminNote = page.getByText('「システム管理者」を選択したため');
  await expect(office).toBeVisible();
  await expect(adminNote).toBeHidden();
  await page.getByLabel('システム管理者').check();
  await expect(office).toBeHidden();
  await expect(page.locator('input[name="department"]').first()).toBeHidden();
  await expect(adminNote).toBeVisible();
  await page.getByLabel('一般職員').check();
  await expect(office).toBeVisible();
  await expect(adminNote).toBeHidden();

  // 一般職員として登録する。
  await page.locator('input[name="user_id"]').fill(newUserId);
  await page.locator('input[name="name"]').fill('E2E 新規職員');
  await page.locator('input[name="password"]').fill(initialPassword);
  await page.locator('input[name="password_confirmation"]').fill(initialPassword);
  await office.selectOption({ label: 'みどり土木事務所' });
  await page.getByLabel('道路').check();
  await page.getByRole('button', { name: '登録する' }).click();
  await expect(page.locator('.alert-success')).toContainText(`ユーザー「${newUserId}」を登録しました。`);
  await logout(page);

  // 初回ログインでは、ダッシュボードではなくパスワード変更画面に進む。
  await page.getByLabel('ユーザーID').fill(newUserId);
  await page.getByLabel('パスワード').fill(initialPassword);
  await page.getByRole('button', { name: 'ログイン' }).click();
  await expect(page).toHaveURL(/\/password\/change$/);
  await expect(page.getByText('初期パスワードのままログインしています。')).toBeVisible();

  // 変更するまでは、ほかの画面を開いてもパスワード変更画面に戻される。
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/password\/change$/);

  await page.getByLabel('現在のパスワード').fill(initialPassword);
  await page.getByLabel('新しいパスワード', { exact: true }).fill(newPassword);
  await page.getByLabel('新しいパスワード（確認）').fill(newPassword);
  await page.getByRole('button', { name: '変更する' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.locator('.alert-success')).toHaveText('パスワードを変更しました。');

  // 新しいパスワードでログインし直せる。
  await logout(page);
  await login(page, newUserId, newPassword);
});
