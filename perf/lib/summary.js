/**
 * 試験結果を「画面ごとの応答時間の表」にまとめる（k6 の handleSummary から呼ぶ）。
 *
 * 表の行は、thresholds に `http_req_duration{name:...}` を書いた画面だけが出る
 * （k6 は thresholds に書いたタグの組み合わせだけを個別に集計するため）。
 * 結果は標準出力と perf/results/ の Markdown に同じものを出す。
 */

const fmt = (v) => (v === undefined ? '-' : `${Math.round(v)}`);

export function buildSummary(data, scenarioName) {
  const rows = [];
  for (const [key, metric] of Object.entries(data.metrics)) {
    const m = /^http_req_duration\{name:(.+)\}$/.exec(key);
    if (!m) continue;
    const v = metric.values;
    const passed = Object.values(metric.thresholds || {}).every((t) => t.ok);
    const count = data.metrics[`http_reqs{name:${m[1]}}`]?.values.count;
    rows.push(
      `| ${m[1]} | ${count ?? '-'} | ${fmt(v.med)} | ${fmt(v['p(95)'])} | ${fmt(v['p(99)'])} | ${fmt(v.max)} | ${passed ? 'OK' : '**NG**'} |`,
    );
  }

  const failed = data.metrics.http_req_failed?.values.rate ?? 0;
  const checks = data.metrics.checks?.values.rate ?? 0;
  const reqs = data.metrics.http_reqs?.values;
  const vus = data.metrics.vus_max?.values.max;

  const lines = [
    `## ${scenarioName}（${new Date().toISOString()}）`,
    '',
    `- 最大同時ユーザー数（VU）: ${vus}`,
    `- リクエスト総数: ${reqs?.count}（${(reqs?.rate ?? 0).toFixed(1)} 件/秒）`,
    `- 失敗率: ${(failed * 100).toFixed(2)}% ／ チェック成功率: ${(checks * 100).toFixed(2)}%`,
    '',
    '| 画面 | 件数 | 中央値(ms) | p95(ms) | p99(ms) | 最大(ms) | 目安 |',
    '|---|---:|---:|---:|---:|---:|---|',
    ...rows.sort(),
    '',
  ];
  return lines.join('\n');
}

export function summaryOutputs(data, scenarioName) {
  const text = buildSummary(data, scenarioName);
  const dir = __ENV.RESULT_DIR || 'perf/results';
  return {
    stdout: `\n${text}\n`,
    [`${dir}/${scenarioName}.md`]: text,
    [`${dir}/${scenarioName}.json`]: JSON.stringify(data, null, 2),
  };
}
