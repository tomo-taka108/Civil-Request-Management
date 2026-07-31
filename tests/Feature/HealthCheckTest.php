<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ALB（ロードバランサ）ヘルスチェック用エンドポイントのテスト。
 * インフラ設計書 3.5：認証なしで 200 を返すこと（unhealthy 判定を防ぐ）。
 */
class HealthCheckTest extends TestCase
{
    public function test_ヘルスチェックは未ログインでも200を返す(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_ヘルスチェックはログイン画面へリダイレクトしない(): void
    {
        // auth ミドルウェア配下ではないこと（ALB が認証なしで叩けること）を保証する。
        $this->get('/health')->assertOk();
    }
}
