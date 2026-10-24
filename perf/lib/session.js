/**
 * 試験用職員のログインと、画面操作の共通処理。
 *
 * このアプリは画面（HTML）を返す作りで、ログイン状態はセッション Cookie で持つ。
 * k6 は仮想ユーザー（VU）ごとに Cookie を自動で保持するので、各 VU が最初に1回ログインすれば、
 * 以降のリクエストはログイン済みとして扱われる。
 *
 * フォームの送信（POST）には CSRF トークンが要る。画面の HTML に埋め込まれている
 * `<input type="hidden" name="_token" value="...">` を読み取って一緒に送る。
 */
import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8082';

/** PerfDataSeeder と同じ値（試験専用 DB にしか存在しない）。 */
const PASSWORD = 'perf-password';
const STAFF_COUNT = 30;
const DEPARTMENTS = ['road', 'river', 'sabo'];

/**
 * VU 番号から、その VU が使う試験用職員を決める（perf01〜perf30）。
 * 部署の割り当ては PerfDataSeeder::seedUsers と同じ規則。
 */
export function staffForVu(vu) {
  const n = ((vu - 1) % STAFF_COUNT) + 1;
  return {
    userId: `perf${String(n).padStart(2, '0')}`,
    department: DEPARTMENTS[((n - 1) % 10) % 3],
  };
}

export function csrfToken(html) {
  const m = /name="_token" value="([^"]+)"/.exec(html || '');
  return m ? m[1] : null;
}

/** ログイン画面を開き、ID・パスワードを送ってダッシュボードまで進む。 */
export function login(userId) {
  const form = http.get(`${BASE_URL}/login`, { tags: { name: 'GET /login' } });
  const token = csrfToken(form.body);
  if (!token) {
    fail(`ログイン画面から CSRF トークンを読めなかった（status=${form.status}）`);
  }

  const res = http.post(
    `${BASE_URL}/login`,
    { _token: token, user_id: userId, password: PASSWORD },
    // リダイレクト先（ダッシュボード）まで同じ名前で数えないよう、POST だけを測る。
    { tags: { name: 'POST /login' }, redirects: 0 },
  );
  const ok = check(res, {
    'ログインできた': (r) => r.status === 302 && !(r.headers.Location || '').includes('/login'),
  });
  if (!ok) {
    fail(`${userId} でログインできなかった（status=${res.status} url=${res.url}）`);
  }
}

/** 画面を開き、ログイン画面に戻されずに表示できたかを確かめる。 */
export function page(path, name) {
  const res = http.get(`${BASE_URL}${path}`, { tags: { name } });
  check(res, {
    '画面を表示できた': (r) => r.status === 200 && !r.url.includes('/login'),
  });
  return res;
}

/** 一覧画面の HTML から案件 ID を拾う（行のリンク先 /requests/{id}）。 */
export function requestIds(html) {
  const ids = [];
  const re = /\/requests\/(\d+)'/g;
  let m;
  while ((m = re.exec(html || '')) !== null) {
    ids.push(Number(m[1]));
  }
  return ids;
}

export function pick(list) {
  return list[Math.floor(Math.random() * list.length)];
}

export function between(min, max) {
  return min + Math.random() * (max - min);
}
