<?php

/*
 * サンプル現場写真の縮小（docs/sample-photos.md）。
 *
 * originals/ にある AI 生成の元画像（PNG 等・1枚数MB。Git 管理外）を、
 * 横 1280px の JPEG に縮小してこのフォルダに書き出す（Git 管理・SampleRequestSeeder が読む）。
 * 写真機能はサムネイルを別に作らず元画像を縮めて表示するため、軽くしておく。
 *
 * 実行：docker compose exec -T php php database/seeders/photos/resize.php
 */

const MAX_WIDTH = 1280;
const JPEG_QUALITY = 82;

$sourceDir = __DIR__.'/originals';
$files = glob($sourceDir.'/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];

if ($files === []) {
    fwrite(STDERR, "元画像がありません：{$sourceDir}\n");
    exit(1);
}

foreach ($files as $file) {
    $image = imagecreatefromstring((string) file_get_contents($file));
    if ($image === false) {
        fwrite(STDERR, '読み込めません：'.basename($file)."\n");
        exit(1);
    }

    if (imagesx($image) > MAX_WIDTH) {
        $scaled = imagescale($image, MAX_WIDTH, -1, IMG_BICUBIC);
        if ($scaled === false) {
            fwrite(STDERR, '縮小できません：'.basename($file)."\n");
            exit(1);
        }
        $image = $scaled;
    }

    $output = __DIR__.'/'.strtolower(pathinfo($file, PATHINFO_FILENAME)).'.jpg';
    imagejpeg($image, $output, JPEG_QUALITY);

    printf("%s → %s（%dx%d・%dKB）\n", basename($file), basename($output), imagesx($image), imagesy($image), intdiv((int) filesize($output), 1024));
}
