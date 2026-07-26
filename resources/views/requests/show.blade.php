@extends('layouts.app')

@section('title', '案件詳細')
@section('nav-requests', 'active')

@php
    $receptionMethods = ['window' => '窓口', 'phone' => '電話', 'email' => 'メール', 'letter' => '要望書', 'fax' => 'FAX', 'patrol' => '職員パトロール', 'other' => 'その他'];
    $requesterCategories = ['individual' => '個人', 'neighborhood_association' => '自治会', 'municipality' => '市町村', 'council_member' => '議員', 'anonymous' => '匿名', 'staff_patrol' => '職員パトロール', 'other' => 'その他'];
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $requestTypes = ['complaint' => '苦情', 'request' => '要望', 'anomaly' => '異常発見'];
    $necessities = ['yes' => 'あり', 'no' => 'なし', 'unknown' => '不明'];
    $urgencies = ['high' => '高', 'medium' => '中', 'low' => '低'];
    $statuses = ['not_started' => '未対応', 'in_progress' => '対応中', 'completed' => '対応完了'];
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1 class="page-title">案件詳細（受付番号：{{ $request->reception_number }}）</h1>
        <div style="display:flex; gap:10px;">
            <a class="btn btn-secondary" href="{{ route('requests.index') }}">一覧に戻る</a>
            {{-- 編集画面（#6）は次フェーズ。編集権限がある場合のみボタンを表示する（画面設計書 3.2） --}}
            @can('update', $request)
                @if (Route::has('requests.edit'))
                    <a class="btn btn-primary" href="{{ route('requests.edit', $request) }}">編集する</a>
                @endif
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="section-heading">管理情報</div>
        <dl class="detail-grid">
            <dt>受付番号</dt><dd>{{ $request->reception_number }}</dd>
            <dt>受付日時</dt><dd>{{ $request->reception_date->format('Y-m-d') }} {{ \Illuminate\Support\Str::substr($request->reception_time, 0, 5) }}</dd>
            <dt>受付方法</dt>
            <dd>
                {{ $receptionMethods[$request->reception_method] ?? $request->reception_method }}
                @if ($request->reception_method === 'other' && $request->reception_method_other)
                    （{{ $request->reception_method_other }}）
                @endif
            </dd>
            <dt>登録者（受付職員）</dt><dd>{{ $request->registeredBy?->name ?? '-' }}</dd>
            <dt>最終更新日時</dt><dd>{{ $request->updated_at->format('Y-m-d H:i') }}</dd>
        </dl>

        <div class="section-heading">相手方情報</div>
        <dl class="detail-grid">
            <dt>区分</dt><dd>{{ $requesterCategories[$request->requester_category] ?? $request->requester_category }}</dd>
            <dt>要望者</dt><dd>{{ $request->requester_name ?: '-' }}</dd>
        </dl>

        <div class="section-heading">案件情報</div>
        <dl class="detail-grid">
            <dt>対応部署</dt><dd>{{ $departments[$request->department] ?? $request->department }}</dd>
            <dt>種別</dt><dd>{{ $requestTypes[$request->request_type] ?? $request->request_type }}</dd>
            <dt>要望の内容</dt><dd style="white-space:pre-wrap;">{{ $request->content }}</dd>
            <dt>要望箇所</dt>
            <dd>
                {{ $request->address ?: '（住所未登録）' }}
                @if ($request->latitude !== null && $request->longitude !== null)
                    （緯度: {{ $request->latitude }}, 経度: {{ $request->longitude }}）
                    {{-- 地図（Leaflet）でのピン表示は次フェーズ（#7） --}}
                    <div class="map-placeholder" style="margin-top:8px;">地図でのピン表示は今後対応予定</div>
                @endif
            </dd>
        </dl>

        <div class="section-heading">対応情報</div>
        <dl class="detail-grid">
            <dt>対応の必要性</dt><dd>{{ $necessities[$request->response_necessity] ?? $request->response_necessity }}</dd>
            <dt>緊急性</dt><dd><span class="badge badge-urgency-{{ $urgencies[$request->urgency] ?? '' }}">{{ $urgencies[$request->urgency] ?? $request->urgency }}</span></dd>
            <dt>対応方針</dt><dd style="white-space:pre-wrap;">{{ $request->response_policy ?: '-' }}</dd>
            <dt>対応状況</dt><dd><span class="badge badge-status-{{ $statuses[$request->response_status] ?? '' }}">{{ $statuses[$request->response_status] ?? $request->response_status }}</span></dd>
            <dt>対応完了日</dt><dd>{{ $request->response_completed_date?->format('Y-m-d') ?? '-' }}</dd>
        </dl>
    </div>
@endsection
