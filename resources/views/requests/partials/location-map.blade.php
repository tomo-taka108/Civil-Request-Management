{{--
    要望箇所の地図（Leaflet + 国土地理院タイル）。詳細・登録・編集で共用する部品。

    パラメータ:
    - $mapId      … 地図コンテナのDOM id（1画面に複数置ける想定で必須）
    - $editable   … true なら地図クリック/ドラッグで座標を設定できる（登録・編集）。
                    false なら表示のみ（詳細）。
    - $latitude   … 初期緯度（null 可）
    - $longitude  … 初期経度（null 可）
    - $latName    … 編集時に座標を書き込む input の name（$editable のときのみ使用）
    - $lngName    … 同上（経度）

    Leaflet の CSS/JS はレイアウトの @stack('head') / @stack('scripts') に積む。
    複数画面で読み込んでも二重にならないよう @once で1回だけ出力する。
--}}
@once
    @push('head')
        <link rel="stylesheet" href="{{ versioned_asset('vendor/leaflet/leaflet.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ versioned_asset('vendor/leaflet/leaflet.js') }}"></script>
    @endpush
@endonce

@php
    $editable = $editable ?? false;
    $latName = $latName ?? 'latitude';
    $lngName = $lngName ?? 'longitude';

    // old() は空文字を返すことがある（クリア後の再送信など）。空文字は「座標なし」
    // として扱い、[0,0] に誤ってピンが立つのを防ぐ。
    $lat = ($latitude === null || $latitude === '') ? null : (float) $latitude;
    $lng = ($longitude === null || $longitude === '') ? null : (float) $longitude;
    $hasPoint = $lat !== null && $lng !== null;
@endphp

<div id="{{ $mapId }}" class="location-map" style="height:320px; border-radius:var(--radius); overflow:hidden;"></div>

@if ($editable)
    <div class="note" style="margin-top:6px;">
        地図をクリックすると要望箇所を設定できます（ピンはドラッグで移動可）。
        <button type="button" class="btn-link" data-map-clear="{{ $mapId }}" style="@if(!$hasPoint) display:none; @endif">位置をクリア</button>
    </div>
    <input type="hidden" name="{{ $latName }}" id="{{ $mapId }}-lat" value="{{ $lat }}">
    <input type="hidden" name="{{ $lngName }}" id="{{ $mapId }}-lng" value="{{ $lng }}">
@endif

@push('scripts')
    <script>
        (function () {
            const mapId = @json($mapId);
            const editable = @json((bool) $editable);
            const initLat = @json($lat);
            const initLng = @json($lng);
            const hasPoint = initLat !== null && initLng !== null;

            // 初期表示：ピンがあればその位置、なければ日本全体。
            const center = hasPoint ? [initLat, initLng] : [36.2048, 138.2529];
            const zoom = hasPoint ? 15 : 5;

            const map = L.map(mapId).setView(center, zoom);
            L.tileLayer('https://cyberjapandata.gsi.go.jp/xyz/std/{z}/{x}/{y}.png', {
                attribution: "<a href='https://maps.gsi.go.jp/development/ichiran.html' target='_blank' rel='noopener'>地理院タイル</a>",
                maxZoom: 18,
            }).addTo(map);

            let marker = null;

            function setMarker(latlng) {
                if (marker) {
                    marker.setLatLng(latlng);
                } else {
                    marker = L.marker(latlng, { draggable: editable }).addTo(map);
                    if (editable) {
                        marker.on('dragend', function () {
                            writeInputs(marker.getLatLng());
                        });
                    }
                }
            }

            function writeInputs(latlng) {
                if (!editable) { return; }
                // decimal:6 に合わせて小数6桁に丸める。
                document.getElementById(mapId + '-lat').value = latlng.lat.toFixed(6);
                document.getElementById(mapId + '-lng').value = latlng.lng.toFixed(6);
                const clearBtn = document.querySelector('[data-map-clear="' + mapId + '"]');
                if (clearBtn) { clearBtn.style.display = ''; }
            }

            if (hasPoint) {
                setMarker([initLat, initLng]);
            }

            if (editable) {
                map.on('click', function (e) {
                    setMarker(e.latlng);
                    writeInputs(e.latlng);
                });

                const clearBtn = document.querySelector('[data-map-clear="' + mapId + '"]');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        if (marker) { map.removeLayer(marker); marker = null; }
                        document.getElementById(mapId + '-lat').value = '';
                        document.getElementById(mapId + '-lng').value = '';
                        clearBtn.style.display = 'none';
                    });
                }
            }
        })();
    </script>
@endpush
