<?php

use Illuminate\Support\Facades\File;

if (! function_exists('versioned_asset')) {
    /**
     * public 配下の静的ファイルに更新時刻クエリを付けた URL を返す（キャッシュバスティング）。
     *
     * 例: versioned_asset('css/common.css') => /css/common.css?v=1721980800
     *
     * ファイルを更新すると更新時刻（?v=）が変わるため、ブラウザは古いキャッシュを
     * 使わず最新版を読み込む。利用者にスーパーリロードを強いずに済む。
     */
    function versioned_asset(string $path): string
    {
        $fullPath = public_path($path);
        $version = File::exists($fullPath) ? File::lastModified($fullPath) : null;

        return asset($path).($version ? "?v={$version}" : '');
    }
}
