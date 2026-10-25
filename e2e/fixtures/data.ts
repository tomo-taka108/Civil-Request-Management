/**
 * 試験データ（database/seeders/E2eDataSeeder.php）と対応する値。
 * Seeder を変えたら、ここも合わせて変える。
 */

/** 試験用ユーザーの共通パスワード（試験専用 DB にしか存在しない）。 */
export const PASSWORD = 'e2e-password';

export const USERS = {
  /** みどり土木事務所・道路担当 */
  road: 'e2e_road',
  /** みどり土木事務所・河川担当（道路の案件は閲覧のみ） */
  river: 'e2e_river',
  /** つばき土木事務所・道路担当（地図のピンの数を確かめるのに使う。ほかのテストはこの事務所に案件を足さない） */
  tsubaki: 'e2e_tsubaki',
  /** システム管理者 */
  admin: 'e2e_admin',
} as const;

/** 試験データの案件（内容の文面で探す）。 */
export const REQUESTS = {
  /** みどり・道路・緊急性 高・位置あり */
  midoriRoad: '【E2E】交差点付近の舗装に大きな穴があり、通行の妨げになっている。',
  /** つばき・道路・緊急性 高・位置あり */
  tsubakiHigh: '【E2E】カーブミラーが倒れている。',
  /** つばき・道路・緊急性 低・位置あり */
  tsubakiLow: '【E2E】歩道の段差でつまずきやすい。',
} as const;

/** つばき土木事務所の案件のうち、位置が登録されているもの（地図のピンの数）。 */
export const TSUBAKI_PIN_COUNT = 2;
