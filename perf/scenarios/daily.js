/**
 * 普段の業務の負荷試験（パフォーマンステスト設計書 4章）。
 *
 * 試験用職員30人（要件定義書 4.2 の想定同時利用者数）が、それぞれ次の「業務の一回り」を
 * 繰り返す。操作の合間には人が画面を読む・考える時間（2〜5秒）を入れる。
 *
 *   ダッシュボード → 一覧 → 条件を変えて検索 → 案件の詳細
 *   ときどき（20%）地図を開く／ときどき（10%）自分の部署の案件に対応履歴を1件書く
 *
 * 1人が大量に入力するような、業務で起きない操作はしない。ほとんどが「見る」操作で、
 * 「書く」操作は少しだけ混ぜる。
 *
 * 実行は perf/run-perf.sh から（試験専用の環境の起動・データ投入・片付けを含む）。
 * 人数を変えるときは PEAK_VUS（例：PEAK_VUS=60 ./perf/run-perf.sh）。
 */
import { sleep } from 'k6';
import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, between, csrfToken, login, page, pick, requestIds, staffForVu } from '../lib/session.js';
import { summaryOutputs } from '../lib/summary.js';

const PEAK_VUS = Number(__ENV.PEAK_VUS || 30);

/** 合否の目安：画面ごとに p95 が1秒未満（設計書 1章）。 */
const P95 = ['p(95)<1000'];
const SCREENS = [
  'GET /dashboard',
  'GET /requests',
  'GET /requests?検索',
  'GET /requests/:id',
  'GET /map',
  'GET /map/pins',
  'POST /requests/:id/logs',
];

const thresholds = {
  http_req_failed: ['rate<0.01'],
  checks: ['rate>0.99'],
  // ログインは試験の最初に1人1回だけ。パスワードの照合（bcrypt）でわざと重く作ってあるため、記録のみ。
  'http_req_duration{name:POST /login}': ['p(95)<3000'],
  'http_reqs{name:POST /login}': ['count>=0'],
};
for (const name of SCREENS) {
  thresholds[`http_req_duration{name:${name}}`] = P95;
  thresholds[`http_reqs{name:${name}}`] = ['count>=0']; // 件数を表に出すため
}

export const options = {
  scenarios: {
    daily: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: Math.ceil(PEAK_VUS / 3) }, // 始業：少しずつ増える
        { duration: '30s', target: PEAK_VUS }, // 全員がそろう
        { duration: '2m', target: PEAK_VUS }, // 全員が使い続ける（ここが本番）
        { duration: '20s', target: 0 }, // 終わる
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds,
  // k6 は既定で、繰り返しの1回ごとに Cookie を捨てる。ログイン（セッション Cookie）を持ち越すため止める。
  noCookiesReset: true,
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
};

/** 検索条件の候補（一覧画面で実際に使われそうな組み合わせ）。 */
const SEARCHES = [
  'response_status[]=not_started&response_status[]=in_progress',
  'urgency[]=high',
  'keyword=%E5%9C%9F%E7%A0%82', // 土砂
  'keyword=%E8%8D%89', // 草
  'keyword=%E8%AD%B7%E5%B2%B8', // 護岸
  'reception_date_from=2026-01-01&reception_date_to=2026-06-30',
  'stale=1',
];

let loggedIn = false;

export default function () {
  const me = staffForVu(__VU);
  if (!loggedIn) {
    login(me.userId);
    loggedIn = true;
    sleep(between(1, 3));
  }

  page('/dashboard', 'GET /dashboard');
  sleep(between(2, 5));

  const list = page('/requests', 'GET /requests');
  sleep(between(2, 5));

  const found = page(`/requests?${pick(SEARCHES)}`, 'GET /requests?検索');
  sleep(between(2, 5));

  const ids = requestIds(found.body).concat(requestIds(list.body));
  if (ids.length > 0) {
    page(`/requests/${pick(ids)}`, 'GET /requests/:id');
    sleep(between(3, 6));
  }

  if (Math.random() < 0.2) {
    // 地図は画面を開いたあと、ブラウザの JavaScript がピンのデータ（JSON）を取りに来る。
    page('/map', 'GET /map');
    page('/map/pins', 'GET /map/pins');
    sleep(between(3, 6));
  }

  if (Math.random() < 0.1) {
    writeLog(me.department);
    sleep(between(2, 5));
  }
}

/** 自分の部署の案件を開き、対応履歴を1件書く（編集できるのは自分の部署の案件だけ）。 */
function writeLog(department) {
  const mine = page(
    `/requests?department[]=${department}&response_status[]=in_progress`,
    'GET /requests?検索',
  );
  const ids = requestIds(mine.body);
  if (ids.length === 0) return;

  const id = pick(ids);
  const detail = page(`/requests/${id}`, 'GET /requests/:id');
  sleep(between(5, 10)); // 対応内容を入力している時間

  const today = new Date(Date.now() + 9 * 3600 * 1000).toISOString().slice(0, 10); // 日本時間の今日
  const res = http.post(
    `${BASE_URL}/requests/${id}/logs`,
    { _token: csrfToken(detail.body), logged_on: today, content: '負荷試験：現地を確認した。' },
    { tags: { name: 'POST /requests/:id/logs' }, redirects: 0 }, // POST だけを測る
  );
  check(res, {
    // 成功すると詳細画面へ戻る。入力エラーなら入力元へ戻される。
    '対応履歴を登録できた': (r) => r.status === 302 && (r.headers.Location || '').endsWith(`/requests/${id}`),
  });
}

export function handleSummary(data) {
  return summaryOutputs(data, 'daily');
}
