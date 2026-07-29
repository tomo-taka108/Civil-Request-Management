<?php

namespace App\Support;

use App\Models\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * 案件（Request）の検索条件をクエリに適用する共通ロジック。
 *
 * 一覧（RequestController::index）・CSV出力（exportCsv）・地図表示
 * （MapController）で「まったく同じ絞り込み・並び順」を使うため、条件適用を
 * ここに一元化する（画面設計書 2章：一覧画面と地図画面で検索条件を揃える）。
 *
 * 項目間はAND、複数選択項目内はOR（whereIn）。enum系の許可値チェックは
 * SearchRequestRequest 側で済んでいる前提で、ここでは値の適用のみ行う。
 * 事務所スコープは Request モデルの OfficeScope が自動適用するため扱わない。
 */
class RequestSearch
{
    /**
     * 検索条件を適用したクエリを返す。
     *
     * $groupByOffice が true のときは事務所ごとにまとめて（事務所内は受付日時の
     * 降順）並べる。管理者の一覧で事務所グループ表示がページをまたいでも崩れない
     * ようにするための並び順（RequestController::index で使用）。
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Request>
     */
    public static function query(array $filters, bool $groupByOffice = false): Builder
    {
        $query = Request::query();

        self::applyFilters($query, $filters);

        if ($groupByOffice) {
            $query->orderBy('office_id');
        }

        return $query
            ->orderByDesc('reception_date')
            ->orderByDesc('reception_time');
    }

    /**
     * 検索条件をクエリに適用する（項目間はAND、複数選択項目内はOR）。
     *
     * @param  Builder<Request>  $query
     * @param  array<string, mixed>  $filters
     */
    private static function applyFilters(Builder $query, array $filters): void
    {
        // 受付日（期間指定）
        if (! empty($filters['reception_date_from'])) {
            $query->where('reception_date', '>=', $filters['reception_date_from']);
        }
        if (! empty($filters['reception_date_to'])) {
            $query->where('reception_date', '<=', $filters['reception_date_to']);
        }

        // 事務所（複数選択＝OR。管理者のみ画面に表示。一般職員は事務所スコープで
        // 自事務所に限定されるため、指定されても実質的な影響はない）。
        if (! empty($filters['office'])) {
            $query->whereIn($query->getModel()->getTable().'.office_id', $filters['office']);
        }

        // 対応部署・対応状況・緊急性（複数選択＝OR。whereIn）
        if (! empty($filters['department'])) {
            $query->whereIn('department', $filters['department']);
        }
        if (! empty($filters['response_status'])) {
            $query->whereIn('response_status', $filters['response_status']);
        }
        if (! empty($filters['urgency'])) {
            $query->whereIn('urgency', $filters['urgency']);
        }

        // 地区・場所（住所の部分一致）。LIKE のワイルドカードはエスケープする。
        if (! empty($filters['address'])) {
            $query->where('address', 'like', '%'.self::escapeLike($filters['address']).'%');
        }

        // キーワード（要望内容の部分一致）。FULLTEXT精度検証は今後の課題のため
        // まずは LIKE で確実に部分一致させる（database-design.md 5章）。
        if (! empty($filters['keyword'])) {
            $query->where('content', 'like', '%'.self::escapeLike($filters['keyword']).'%');
        }
    }

    /**
     * LIKE 検索用に特殊文字（\ % _）をエスケープする。
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
