/**
 * すべてのテストが使う共通の準備（Playwright の fixture）。
 *
 * specs/ のテストは '@playwright/test' ではなく、このファイルから test と expect を読み込む。
 * そうすることで、次の2つが全テストに自動でかかる。
 *
 * 1. 地図タイルをダミー画像に差し替える（設計書 5章）
 *    国土地理院のサーバーの調子や回線でテストが落ちないようにし、相手のサーバーにも
 *    負担をかけないため。差し替えるのは背景の画像だけで、ピンの表示・地図のクリックなど
 *    このアプリの JavaScript は本物のまま動く。
 *    本物を読み込みたいテストは test.use({ realTiles: true }) で切り替える。
 *
 * 2. 画面の JavaScript でエラーが起きたら、テストを失敗にする
 *    画面の見た目が正しくても、裏でエラーが出ていれば別の操作が壊れている可能性があるため。
 */

import { test as base, expect, type Page } from '@playwright/test';
import { PASSWORD } from './data.ts';
import { solidPng } from './png.ts';

/** 国土地理院の地図タイルの URL。 */
export const GSI_TILE_URL = 'https://cyberjapandata.gsi.go.jp/xyz/std/**';

/** タイルの代わりに返す画像（256×256 の薄い灰色）。 */
const DUMMY_TILE = solidPng(256, 256, [230, 230, 230]);

type Options = {
  /** true なら国土地理院の本物のタイルを読み込む（tiles-external.spec.ts だけで使う）。 */
  realTiles: boolean;
};

type Fixtures = {
  /** ダミーに差し替えたタイルの枚数（地図が実際にタイルを要求したことの確認に使う）。 */
  tileRequests: { count: number };
  pageErrors: void;
};

export const test = base.extend<Options & Fixtures>({
  realTiles: [false, { option: true }],

  tileRequests: [
    async ({ page, realTiles }, use) => {
      const counter = { count: 0 };
      if (!realTiles) {
        await page.route(GSI_TILE_URL, (route) => {
          counter.count++;
          return route.fulfill({ status: 200, contentType: 'image/png', body: DUMMY_TILE });
        });
      }
      await use(counter);
    },
    { auto: true },
  ],

  pageErrors: [
    async ({ page }, use) => {
      const errors: string[] = [];
      page.on('pageerror', (error) => errors.push(error.message));
      await use();
      expect(errors, '画面の JavaScript でエラーが起きた').toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };

/** ログインして、ダッシュボードが開くまで待つ。 */
export async function login(page: Page, userId: string, password: string = PASSWORD): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('ユーザーID').fill(userId);
  await page.getByLabel('パスワード').fill(password);
  await page.getByRole('button', { name: 'ログイン' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

/** 画面右上の「ログアウト」から抜け、ログイン画面に戻ったことを確かめる。 */
export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'ログアウト' }).click();
  await expect(page).toHaveURL(/\/login$/);
}
