@extends('layouts.app')

@section('title', '案件一覧')
@section('nav-requests', 'active')

@php
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $requestTypes = ['complaint' => '苦情', 'request' => '要望', 'anomaly' => '異常発見'];
    $urgencies = ['high' => '高', 'medium' => '中', 'low' => '低'];
    $statuses = ['not_started' => '未対応', 'in_progress' => '対応中', 'completed' => '対応完了'];

    // 検索条件の選択状態（複数選択はチェックボックスの checked 判定に使う）
    $selectedDepartments = (array) ($filters['department'] ?? []);
    $selectedStatuses = (array) ($filters['response_status'] ?? []);
    $selectedUrgencies = (array) ($filters['urgency'] ?? []);
@endphp

@section('content')
    <h1 class="page-title">案件一覧</h1>

    <div class="card">
        <form class="search-form" method="GET" action="{{ route('requests.index') }}">
            <div class="field">
                <label>受付日時（期間）</label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="date" name="reception_date_from" value="{{ $filters['reception_date_from'] ?? '' }}">
                    <span>〜</span>
                    <input type="date" name="reception_date_to" value="{{ $filters['reception_date_to'] ?? '' }}">
                </div>
                @error('reception_date_from')<div class="error">{{ $message }}</div>@enderror
                @error('reception_date_to')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label>対応部署</label>
                <div class="checkbox-group">
                    @foreach ($departments as $value => $label)
                        <label><input type="checkbox" name="department[]" value="{{ $value }}" @checked(in_array($value, $selectedDepartments, true))>{{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="field">
                <label>対応状況</label>
                <div class="checkbox-group">
                    @foreach ($statuses as $value => $label)
                        <label><input type="checkbox" name="response_status[]" value="{{ $value }}" @checked(in_array($value, $selectedStatuses, true))>{{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="field">
                <label>緊急性</label>
                <div class="checkbox-group">
                    @foreach ($urgencies as $value => $label)
                        <label><input type="checkbox" name="urgency[]" value="{{ $value }}" @checked(in_array($value, $selectedUrgencies, true))>{{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="field">
                <label>地区・場所</label>
                <input type="text" name="address" value="{{ $filters['address'] ?? '' }}" placeholder="住所の一部を入力">
            </div>
            <div class="field">
                <label>キーワード</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="要望内容の一部を入力">
            </div>
            <div class="field span-3" style="display:flex; justify-content:flex-end; gap:10px;">
                <a class="btn btn-secondary" href="{{ route('requests.index') }}">条件をクリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div class="note">
                @if ($requests->total() > 0)
                    検索結果：{{ number_format($requests->total()) }}件中 {{ number_format($requests->firstItem()) }}〜{{ number_format($requests->lastItem()) }}件を表示
                @else
                    検索結果：0件
                @endif
            </div>
            {{-- CSV出力は別Issueで実装予定 --}}
        </div>

        <table class="list-table">
            <thead>
                <tr>
                    <th>受付番号</th>
                    <th>受付日時</th>
                    <th>対応部署</th>
                    <th>種別</th>
                    <th>要望箇所</th>
                    <th>要望の内容</th>
                    <th>緊急性</th>
                    <th>対応状況</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $request)
                    {{-- 行クリックによる詳細遷移は詳細画面（requests.show）実装後に有効化する --}}
                    <tr>
                        <td>{{ $request->reception_number }}</td>
                        <td>{{ $request->reception_date->format('Y-m-d') }} {{ \Illuminate\Support\Str::substr($request->reception_time, 0, 5) }}</td>
                        <td>{{ $departments[$request->department] ?? $request->department }}</td>
                        <td>{{ $requestTypes[$request->request_type] ?? $request->request_type }}</td>
                        <td>{{ $request->address }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($request->content, 40) }}</td>
                        <td><span class="badge badge-urgency-{{ $urgencies[$request->urgency] ?? '' }}">{{ $urgencies[$request->urgency] ?? $request->urgency }}</span></td>
                        <td><span class="badge badge-status-{{ $statuses[$request->response_status] ?? '' }}">{{ $statuses[$request->response_status] ?? $request->response_status }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center; padding:24px 0;">該当する案件はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $requests->links('vendor.pagination.civiltrack') }}
    </div>
@endsection
