<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequestRequest;
use App\Models\Office;
use App\Models\Request;
use App\Support\RequestLabels;
use App\Support\RequestSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * 地図表示（画面設計書 画面#7・要件定義書 2.4）。
 *
 * 要望・異常箇所を地図上にピン表示する。ピンのデータは非同期エンドポイント
 * （pins）が GeoJSON で返し、画面（index）は地図コンテナと検索フォームのみを
 * 描画する。検索条件・事務所スコープは一覧（RequestController::index）と揃える
 * （RequestSearch を共用）。
 */
class MapController extends Controller
{
    /**
     * 地図画面（画面設計書 画面#7）。
     *
     * 検索フォームと地図コンテナを表示する。ピン自体は pins から非同期取得する。
     * 管理者のみ事務所での絞り込みを提供する（一覧と同じ扱い）。
     */
    public function index(SearchRequestRequest $request): View
    {
        $filters = $request->validated();
        $isAdmin = ($request->user()->role ?? null) === 'admin';

        return view('map.index', [
            'filters' => $filters,
            // 管理者のみ事務所での絞り込みを提供する。一般職員には空配列を渡す。
            'offices' => $isAdmin ? Office::orderBy('id')->get() : collect(),
        ]);
    }

    /**
     * ピン取得（GeoJSON。画面設計書 2章）。
     *
     * 一覧と同一の検索条件・事務所スコープを適用し、緯度経度を両方持つ案件だけを
     * FeatureCollection で返す。各 feature の properties にはポップアップ・連動する
     * ピン一覧の表示に必要な項目（受付番号・受付日時・緊急性・対応部署・住所・
     * 要望内容・詳細URL）を含める。
     */
    public function pins(SearchRequestRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $requests = RequestSearch::query($filters)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $features = $requests->map(fn (Request $request): array => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                // GeoJSON は [経度, 緯度] の順（RFC 7946）。
                'coordinates' => [(float) $request->longitude, (float) $request->latitude],
            ],
            'properties' => [
                'id' => $request->id,
                'reception_number' => $request->reception_number,
                'reception_datetime' => $request->reception_date->format('Y-m-d').' '
                    .Str::substr((string) $request->reception_time, 0, 5),
                'department' => RequestLabels::label(RequestLabels::DEPARTMENTS, $request->department),
                'urgency' => $request->urgency,
                'urgency_label' => RequestLabels::label(RequestLabels::URGENCIES, $request->urgency),
                'address' => (string) ($request->address ?? ''),
                'content' => Str::limit((string) $request->content, 60),
                'url' => route('requests.show', $request),
            ],
        ])->all();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
