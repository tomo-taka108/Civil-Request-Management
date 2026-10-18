<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    // 現場写真（要件定義書 §2.8）の保存先。どちらも非公開で、表示は期限付きの
    // 署名付きURLで行う。ローカル開発は local（storage/app/private。Laravel の
    // 署名付きルートで配信）、本番は s3 を指定する（EC2 のコンテナは作り直すと
    // ファイルが消えるため、本番でローカルディスクは使えない）。
    'photo_disk' => env('PHOTO_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // 既定ではディレクトリが 0700 で作られ、artisan をコンテナの root で実行して
            // 作ったフォルダ（サンプル写真の投入など）を Web サーバー（www-data）が読めなくなる。
            // 外部への非公開は署名付きURLで担保しているため、ディレクトリは 0755 にする。
            'permissions' => [
                'dir' => ['public' => 0755, 'private' => 0755],
            ],
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            // 本番（EC2）では key / secret を設定しない。未設定なら AWS SDK が
            // EC2 のインスタンスプロファイル（IAM ロール）の一時認証を自動で使う。
            // token は SSO などの一時認証で動作確認するときに使う。
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'token' => env('AWS_SESSION_TOKEN'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
