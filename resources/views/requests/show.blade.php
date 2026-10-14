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
    <div style="display:flex; justify-content:space-between; align-items:center; gap:16px;">
        <h1 class="page-title">案件詳細（受付番号：{{ $request->reception_number }}）</h1>
        <div style="display:flex; gap:10px; flex-shrink:0;">
            <a class="btn btn-secondary" href="{{ route('requests.index') }}">一覧に戻る</a>
            {{-- 編集画面（#6）は次フェーズ。編集権限がある場合のみボタンを表示する（画面設計書 3.2） --}}
            @can('update', $request)
                @if (Route::has('requests.edit'))
                    <a class="btn btn-primary" href="{{ route('requests.edit', $request) }}">編集する</a>
                @endif
            @endcan
        </div>
    </div>

    {{-- 編集できない一般職員には、その理由を明示する（画面設計書 3.2：編集・削除は
         対応部署が一致する担当者のみ）。管理者・担当部署一致の職員には表示しない。 --}}
    @cannot('update', $request)
        @if (auth()->user()->role === 'staff')
            <div class="alert alert-info">
                この案件の対応部署は「{{ $departments[$request->department] ?? $request->department }}」です。対応部署が異なるため、案件情報の編集・削除はできません（閲覧のみ可能）。編集は対応部署の担当職員または管理者が行えます。
            </div>
        @endif
    @endcannot

    <div class="card">
        <div class="section-heading">管理情報</div>
        <dl class="detail-grid">
            <dt>事務所</dt><dd>{{ $request->office->name }}</dd>
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
                    <div style="margin-top:8px;">
                        @include('requests.partials.location-map', [
                            'mapId' => 'detail-map',
                            'editable' => false,
                            'latitude' => $request->latitude,
                            'longitude' => $request->longitude,
                        ])
                    </div>
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

    {{-- 対応履歴（要件定義書 §2.6）。対応部署一致の職員・管理者は追加・編集・削除ができ、
         他部署の職員は閲覧のみ（partial 内で @can により出し分ける）。 --}}
    <div class="card">
        @include('requests.partials.log-timeline', ['request' => $request])
    </div>

    {{-- 受付時の写真（要件定義書 §2.8）。要望者から提供された写真など、特定の対応履歴に
         紐づかない写真。対応時に撮影した写真は上の対応履歴に紐づけて登録する。 --}}
    @php
        $receptionPhotos = $request->receptionPhotos;
        $maxPhotos = \App\Models\RequestPhoto::MAX_PER_GROUP;
        $receptionErrors = $errors->receptionPhotos;
    @endphp
    <div class="card">
        <div class="section-heading">受付時の写真（要望者提供分など）</div>

        @if ($receptionPhotos->isEmpty())
            <div class="note">受付時の写真は登録されていません。</div>
        @else
            @include('requests.partials.photo-grid', ['request' => $request, 'photos' => $receptionPhotos])
            <div class="note" style="margin-top:12px;">
                サムネイルをクリックすると別タブで拡大表示します。写真は非公開の保存領域に置き、一定時間のみ有効な署名付きURLで表示するため、URLを共有しても第三者は閲覧できません。
            </div>
        @endif

        @can('managePhoto', $request)
            <div class="section-heading">写真を追加</div>
            @if ($receptionPhotos->count() >= $maxPhotos)
                <div class="note">受付時の写真は上限の{{ $maxPhotos }}枚が登録されています。追加するには、先に不要な写真を削除してください。</div>
            @else
                <form class="form-grid" method="POST" action="{{ route('requests.photos.store', $request) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="required">画像ファイル</label>
                    <div>
                        <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple>
                        <div class="note">JPEG・PNG のみ、1ファイル5MBまで、最大{{ $maxPhotos }}枚まで登録できます（現在{{ $receptionPhotos->count() }}枚）。対応時に撮影した写真は、上の対応履歴から登録してください。</div>
                        @if ($receptionErrors->any())
                            <div class="error">{{ $receptionErrors->first('photos') ?: $receptionErrors->first('photos.*') }}</div>
                        @endif
                    </div>
                    <div style="grid-column:2; display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary">写真をアップロードする</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>
@endsection
