@extends('layouts.app')

@section('title', '地図表示')
@section('nav-map', 'active')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('vendor/leaflet/leaflet.css') }}">
@endpush

@php
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $urgencies = ['high' => '高', 'medium' => '中', 'low' => '低'];
    $statuses = ['not_started' => '未対応', 'in_progress' => '対応中', 'completed' => '対応完了'];

    // 検索条件の選択状態（複数選択はチェックボックスの checked 判定に使う）
    $selectedOffices = array_map('strval', (array) ($filters['office'] ?? []));
    $selectedDepartments = (array) ($filters['department'] ?? []);
    $selectedStatuses = (array) ($filters['response_status'] ?? []);
    $selectedUrgencies = (array) ($filters['urgency'] ?? []);
@endphp

@section('content')
    <h1 class="page-title">地図表示</h1>

    <div class="card">
        {{-- 地図画面の検索も一覧と同じクエリ形式（配列）で送る。GETで /map に投げ、
             サーバは同条件でピン(GeoJSON)を返す（画面設計書 2章）。 --}}
        <form class="search-form" method="GET" action="{{ route('map.index') }}">
            @if ($offices->isNotEmpty())
                <div class="field span-3 field-office">
                    <label>事務所</label>
                    <div class="checkbox-group">
                        @foreach ($offices as $office)
                            <label><input type="checkbox" name="office[]" value="{{ $office->id }}" @checked(in_array((string) $office->id, $selectedOffices, true))>{{ $office->name }}</label>
                        @endforeach
                    </div>
                </div>
            @endif
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
                <a class="btn btn-secondary" href="{{ route('map.index') }}">条件をクリア</a>
                <button type="submit" class="btn btn-primary">地図に反映</button>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <div style="display:flex;">
            {{-- 地図エリア（Leaflet + 国土地理院タイル） --}}
            <div id="map" style="flex:1; height:520px;"></div>

            {{-- ピン一覧（地図と連動）。JS が GeoJSON から生成する。 --}}
            <div style="width:300px; border-left:1px solid var(--color-border); max-height:520px; overflow-y:auto;">
                <div id="pin-count" class="note" style="padding:12px 14px 4px;">読み込み中…</div>
                <div id="pin-list"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (function () {
            // 現在の検索条件（クエリ文字列）をそのまま pins に引き継ぐ。
            const pinsUrl = @json(route('map.pins')) + window.location.search;

            const urgencyClass = { high: '高', medium: '中', low: '低' };

            // 国土地理院 標準地図タイル（APIキー不要・無料）。初期表示は日本全体。
            const map = L.map('map').setView([36.2048, 138.2529], 5);
            L.tileLayer('https://cyberjapandata.gsi.go.jp/xyz/std/{z}/{x}/{y}.png', {
                attribution: "<a href='https://maps.gsi.go.jp/development/ichiran.html' target='_blank' rel='noopener'>地理院タイル</a>",
                maxZoom: 18,
            }).addTo(map);

            // 緊急性で色分けした雫型ピン（画像に依存しない DivIcon）。
            function pinIcon(urgency) {
                const cls = urgencyClass[urgency] || '低';
                return L.divIcon({
                    className: '',
                    html: '<span class="map-pin map-pin-urgency-' + cls + ' map-pin-static"></span>',
                    iconSize: [26, 26],
                    iconAnchor: [13, 26],
                    popupAnchor: [0, -26],
                });
            }

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            const listEl = document.getElementById('pin-list');
            const countEl = document.getElementById('pin-count');

            fetch(pinsUrl, { headers: { Accept: 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (geojson) {
                    const features = geojson.features || [];
                    countEl.textContent = '検索結果：' + features.length + '件をピン表示中';

                    if (features.length === 0) {
                        listEl.innerHTML = '<div class="note" style="padding:12px 14px;">該当する案件（位置情報あり）はありません。</div>';
                        return;
                    }

                    const markers = [];
                    const bounds = [];

                    features.forEach(function (f, i) {
                        const p = f.properties;
                        const coords = f.geometry.coordinates; // [lng, lat]
                        const latlng = [coords[1], coords[0]];
                        bounds.push(latlng);

                        const cls = urgencyClass[p.urgency] || '低';
                        const marker = L.marker(latlng, { icon: pinIcon(p.urgency) }).addTo(map);
                        marker.bindPopup(
                            '<div style="min-width:180px;">' +
                            '<div style="font-weight:700;">' + esc(p.reception_datetime) + '</div>' +
                            '<div style="font-size:12px;color:#666;">' + esc(p.department) + '／' + esc(p.address) + '</div>' +
                            '<div style="margin:6px 0;">' + esc(p.content) + '</div>' +
                            '<a href="' + esc(p.url) + '">詳細を見る</a>' +
                            '</div>'
                        );
                        markers.push(marker);

                        // 連動するサイドバーの一覧項目。
                        const item = document.createElement('div');
                        item.className = 'map-pin-list-item';
                        item.style.cursor = 'pointer';
                        item.innerHTML =
                            '<div style="display:flex; justify-content:space-between; align-items:center;">' +
                            '<span class="pin-number pin-number-' + cls + '">' + (i + 1) + '</span>' +
                            '<span class="badge badge-urgency-' + cls + '">' + esc(p.urgency_label) + '</span>' +
                            '</div>' +
                            '<div class="pin-title"><a href="' + esc(p.url) + '">' + esc(p.reception_datetime) + '</a></div>' +
                            '<div class="pin-desc">' + esc(p.content) + '</div>' +
                            '<div class="note">' + esc(p.department) + '／' + esc(p.address) + '</div>';
                        // 項目クリックで対応するピンにフォーカスしポップアップを開く
                        // （詳細リンク自体のクリックはそのまま遷移させる）。
                        item.addEventListener('click', function (e) {
                            if (e.target.tagName === 'A') { return; }
                            map.setView(latlng, 14);
                            marker.openPopup();
                        });
                        listEl.appendChild(item);
                    });

                    // すべてのピンが収まるよう表示範囲を調整。
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                })
                .catch(function () {
                    countEl.textContent = 'ピンの読み込みに失敗しました。';
                });
        })();
    </script>
@endpush
