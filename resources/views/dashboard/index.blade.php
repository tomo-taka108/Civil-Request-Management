@extends('layouts.app')

@section('title', 'ダッシュボード')
@section('nav-dashboard', 'active')

@php
    $maxStatus = max(1, max($stats->byStatus));
    $maxUrgency = max(1, max($stats->byUrgency));
@endphp

@section('content')
    <h1 class="page-title">ダッシュボード</h1>

    {{-- ① 要対応サマリー。各カードはクリックすると、そのカードが数えている条件だけで
         絞り込んだ案件一覧へ遷移する（全案件ではない）。 --}}
    <div class="section-heading" style="margin-top:0;">要対応サマリー</div>
    <div class="stat-cards">
        <a class="stat-card is-danger" href="{{ route('requests.index', ['response_status' => ['not_started'], 'urgency' => ['high']]) }}">
            <div class="stat-label">未対応 かつ 緊急性「高」</div>
            <div class="stat-value">{{ $stats->highNotStartedCount }}<span class="stat-unit">件</span></div>
            <div class="stat-note">最優先で着手が必要です</div>
        </a>
        <a class="stat-card is-warning" href="{{ route('requests.index', ['stale' => 1]) }}">
            <div class="stat-label">30日以上 動きなし</div>
            <div class="stat-value">{{ $stats->staleCount }}<span class="stat-unit">件</span></div>
            <div class="stat-note">対応完了していない案件のうち、30日以上対応の動きがないもの（最新の対応日、対応履歴が無ければ受付日から起算）</div>
        </a>
        <a class="stat-card is-info" href="{{ route('requests.index', ['response_status' => ['in_progress']]) }}">
            <div class="stat-label">対応中</div>
            <div class="stat-value">{{ $stats->byStatus['in_progress'] }}<span class="stat-unit">件</span></div>
            <div class="stat-note">着手済み・完了前</div>
        </a>
        <a class="stat-card" href="{{ route('requests.index') }}">
            <div class="stat-label">全案件</div>
            <div class="stat-value">{{ $stats->totalCount }}<span class="stat-unit">件</span></div>
            <div class="stat-note">削除済みを除く（条件を指定しない一覧へ）</div>
        </a>
    </div>

    {{-- ②③ 内訳グラフ。横棒＋数値併記（色だけに依存しない）。 --}}
    <div class="dash-row">
        <div class="card">
            <div class="section-heading">対応状況の内訳</div>
            <div class="bar-chart">
                @foreach (\App\Support\RequestLabels::STATUSES as $value => $label)
                    @php
                        $barClass = match ($value) {
                            'not_started' => 'is-danger',
                            'in_progress' => 'is-warning',
                            'completed' => 'is-success',
                            default => '',
                        };
                        $n = $stats->byStatus[$value];
                    @endphp
                    <div class="bar-label">{{ $label }}</div>
                    <div class="bar-track"><div class="bar-fill {{ $barClass }}" style="width:{{ (int) round($n / $maxStatus * 100) }}%;"></div></div>
                    <div class="bar-value"><a href="{{ route('requests.index', ['response_status' => [$value]]) }}">{{ $n }}件</a></div>
                @endforeach
            </div>
            <div class="note">棒の長さは最大件数（{{ $maxStatus }}件）を100%とした相対表示です。件数をクリックすると、その対応状況の案件一覧を表示します。</div>
        </div>

        <div class="card">
            <div class="section-heading">緊急性の内訳</div>
            <div class="bar-chart">
                @foreach (\App\Support\RequestLabels::URGENCIES as $value => $label)
                    @php
                        $barClass = match ($value) {
                            'high' => 'is-danger',
                            'medium' => 'is-warning',
                            'low' => 'is-muted',
                            default => '',
                        };
                        $n = $stats->byUrgency[$value];
                    @endphp
                    <div class="bar-label">{{ $label }}</div>
                    <div class="bar-track"><div class="bar-fill {{ $barClass }}" style="width:{{ (int) round($n / $maxUrgency * 100) }}%;"></div></div>
                    <div class="bar-value"><a href="{{ route('requests.index', ['urgency' => [$value]]) }}">{{ $n }}件</a></div>
                @endforeach
            </div>
            <div class="note">棒の長さは最大件数（{{ $maxUrgency }}件）を100%とした相対表示です。件数をクリックすると、その緊急性の案件一覧を表示します。</div>
        </div>
    </div>

    {{-- ④ 対応部署 × 対応状況のマトリクス。すべての件数がリンクで、クリックすると
         その条件だけで絞り込んだ一覧へ遷移する。 --}}
    <div class="card">
        <div class="section-heading">対応部署 × 対応状況</div>
        <table class="matrix-table">
            <thead>
                <tr>
                    <th>対応部署</th>
                    @foreach (\App\Support\RequestLabels::STATUSES as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                    <th>計</th>
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Support\RequestLabels::DEPARTMENTS as $deptValue => $deptLabel)
                    @php $row = $stats->matrix[$deptValue]; @endphp
                    <tr>
                        <th>{{ $deptLabel }}</th>
                        @foreach (\App\Support\RequestLabels::STATUSES as $statusValue => $statusLabel)
                            <td><a href="{{ route('requests.index', ['department' => [$deptValue], 'response_status' => [$statusValue]]) }}">{{ $row[$statusValue] }}</a></td>
                        @endforeach
                        <td class="total"><a href="{{ route('requests.index', ['department' => [$deptValue]]) }}">{{ array_sum($row) }}</a></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th>計</th>
                    @foreach (\App\Support\RequestLabels::STATUSES as $statusValue => $statusLabel)
                        <td><a href="{{ route('requests.index', ['response_status' => [$statusValue]]) }}">{{ $stats->byStatus[$statusValue] }}</a></td>
                    @endforeach
                    <td><a href="{{ route('requests.index') }}">{{ $stats->totalCount }}</a></td>
                </tr>
            </tfoot>
        </table>
        <div class="note">件数をクリックすると、その条件で絞り込んだ案件一覧を表示します（計の件数も、部署のみ・対応状況のみ・全件での絞り込みとして機能します）。</div>
    </div>

    {{-- ⑤ 対応を急ぐ案件。緊急性「高」かつ未対応、または30日以上動きのないものを上位10件。
         「未対応」（対応状況の値）と混同されやすいため、見出しは status 名と重ならない
         表記にし、抽出条件を見出しの直下に注釈として置く（リストを見る前に目に入るように）。 --}}
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div class="section-heading" style="margin:0; border:none; padding:0;">対応を急ぐ案件（上位{{ $stats->attentionList->count() }}件）</div>
            <a class="btn btn-secondary" href="{{ route('requests.index') }}">案件一覧で見る</a>
        </div>
        <div class="note" style="margin:6px 0 14px;">※ 緊急性「高」かつ未対応、または30日以上対応の動きがない未完了案件を、緊急性・動きのない順に表示しています。</div>
        @if ($stats->attentionList->isEmpty())
            <div class="note">現在、対応を急ぐ案件はありません。</div>
        @else
            <table class="list-table">
                <thead>
                    <tr>
                        <th>受付番号</th>
                        <th>受付日</th>
                        <th>対応部署</th>
                        <th>緊急性</th>
                        <th>対応状況</th>
                        <th>要望の内容</th>
                        <th>最終対応日</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats->attentionList as $request)
                        <tr onclick="location.href='{{ route('requests.show', $request) }}'">
                            <td>{{ $request->reception_number }}</td>
                            <td>{{ $request->reception_date->format('Y-m-d') }}</td>
                            <td>{{ \App\Support\RequestLabels::label(\App\Support\RequestLabels::DEPARTMENTS, $request->department) }}</td>
                            <td><span class="badge badge-urgency-{{ \App\Support\RequestLabels::label(\App\Support\RequestLabels::URGENCIES, $request->urgency) }}">{{ \App\Support\RequestLabels::label(\App\Support\RequestLabels::URGENCIES, $request->urgency) }}</span></td>
                            <td><span class="badge badge-status-{{ \App\Support\RequestLabels::label(\App\Support\RequestLabels::STATUSES, $request->response_status) }}">{{ \App\Support\RequestLabels::label(\App\Support\RequestLabels::STATUSES, $request->response_status) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($request->content, 40) }}</td>
                            {{-- 最新の対応日。対応履歴が無い案件は受付日（＝受付したまま動いていない）。 --}}
                            <td>{{ \Illuminate\Support\Carbon::parse($request->last_activity_on)->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ⑥ 事務所別の内訳（システム管理者のみ表示）。一般職員は自事務所しか
         集計対象にならないため、このカードは出さない。 --}}
    @if ($stats->isAdmin)
        <div class="card" style="border-color:var(--color-primary);">
            <div class="section-heading">事務所別の内訳</div>
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th>事務所</th>
                        @foreach (\App\Support\RequestLabels::STATUSES as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th>計</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats->offices as $office)
                        @php $row = $stats->officeMatrix[$office->id]; @endphp
                        <tr>
                            <th>{{ $office->name }}</th>
                            @foreach (\App\Support\RequestLabels::STATUSES as $statusValue => $statusLabel)
                                <td><a href="{{ route('requests.index', ['office' => [$office->id], 'response_status' => [$statusValue]]) }}">{{ $row[$statusValue] }}</a></td>
                            @endforeach
                            <td class="total"><a href="{{ route('requests.index', ['office' => [$office->id]]) }}">{{ array_sum($row) }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>計</th>
                        @foreach (\App\Support\RequestLabels::STATUSES as $statusValue => $statusLabel)
                            <td><a href="{{ route('requests.index', ['response_status' => [$statusValue]]) }}">{{ $stats->byStatus[$statusValue] }}</a></td>
                        @endforeach
                        <td><a href="{{ route('requests.index') }}">{{ $stats->totalCount }}</a></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
@endsection
