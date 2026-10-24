/**
 * CSV 出力の試験（パフォーマンステスト設計書 4章）。
 *
 * CSV 出力は条件に合う全件を書き出すため、1回あたりが最も重い操作になる。
 * ただし使う頻度は低い（集計や報告のときに管理者・職員が時々使う）ので、30人の
 * 普段の業務（daily.js）とは分け、1人で数回だけ実行して「全件出すと何秒かかるか」を測る。
 *
 *   - 管理者：全事務所の全件（約3,000件）
 *   - 一般職員：自分の事務所の全件（約1,000件）
 */
import { check, sleep } from 'k6';
import http from 'k6/http';
import { BASE_URL, login } from '../lib/session.js';
import { summaryOutputs } from '../lib/summary.js';

const ROUNDS = 5;

export const options = {
  scenarios: {
    admin: { executor: 'per-vu-iterations', vus: 1, iterations: ROUNDS, env: { WHO: 'perfadmin' } },
    staff: {
      executor: 'per-vu-iterations', vus: 1, iterations: ROUNDS, env: { WHO: 'perf01' },
      startTime: '40s', // 管理者の計測と重ならないよう後から始める
    },
  },
  // k6 は既定で、繰り返しの1回ごとに Cookie を捨てる。ログイン（セッション Cookie）を持ち越すため止める。
  noCookiesReset: true,
  thresholds: {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    // 全件の書き出しなので、普段の画面（1秒）より緩い3秒を目安にする。
    'http_req_duration{name:CSV 出力（管理者・全件）}': ['p(95)<3000'],
    'http_reqs{name:CSV 出力（管理者・全件）}': ['count>=0'],
    'http_req_duration{name:CSV 出力（職員・自事務所）}': ['p(95)<3000'],
    'http_reqs{name:CSV 出力（職員・自事務所）}': ['count>=0'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
};

let loggedIn = false;

export default function () {
  const admin = __ENV.WHO === 'perfadmin';
  if (!loggedIn) {
    login(__ENV.WHO);
    loggedIn = true;
  }

  const res = http.get(`${BASE_URL}/requests-export`, {
    tags: { name: admin ? 'CSV 出力（管理者・全件）' : 'CSV 出力（職員・自事務所）' },
    responseType: 'text',
  });
  const rows = (res.body || '').split('\n').filter((l) => l.trim() !== '').length - 1; // 見出し行を除く
  check(res, {
    'CSV を出力できた': (r) => r.status === 200,
    '件数がそろっている': () => rows >= (admin ? 2900 : 950),
  });
  sleep(3);
}

export function handleSummary(data) {
  return summaryOutputs(data, 'export');
}
