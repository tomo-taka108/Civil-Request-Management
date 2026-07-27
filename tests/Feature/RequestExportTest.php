<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 案件CSV出力（RequestController::exportCsv）のテスト。
 * 事務所スコープ・検索条件の引き継ぎ・BOM・ラベル変換を検証する。
 */
class RequestExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一般職員（事務所所属）を作成してログインさせる。
     */
    private function actingAsStaff(?Office $office = null): User
    {
        $office ??= Office::factory()->create();
        $user = User::factory()->create([
            'office_id' => $office->id,
            'role' => 'staff',
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * StreamedResponse の本文を文字列として取得する。
     */
    private function streamedContent(TestResponse $response): string
    {
        return $response->streamedContent();
    }

    public function test_未ログインはCSV出力にアクセスできない(): void
    {
        $this->get(route('requests.export'))->assertRedirect('/login');
    }

    public function test_CSVをダウンロードできる(): void
    {
        $this->actingAsStaff();

        $response = $this->get(route('requests.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));
    }

    public function test_CSVはBOM付きUTF8で始まる(): void
    {
        $this->actingAsStaff();

        $content = $this->streamedContent($this->get(route('requests.export')));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    public function test_CSVにヘッダ行が含まれる(): void
    {
        $this->actingAsStaff();

        $content = $this->streamedContent($this->get(route('requests.export')));

        $this->assertStringContainsString('受付番号', $content);
        $this->assertStringContainsString('対応部署', $content);
        $this->assertStringContainsString('対応状況', $content);
    }

    public function test_一般職員は自事務所の案件のみCSVに出力される(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $mine = Request::factory()->create(['office_id' => $officeA->id]);
        $others = Request::factory()->create(['office_id' => $officeB->id]);

        $this->actingAsStaff($officeA);

        $content = $this->streamedContent($this->get(route('requests.export')));

        $this->assertStringContainsString($mine->reception_number, $content);
        $this->assertStringNotContainsString($others->reception_number, $content);
    }

    public function test_管理者は全事務所の案件がCSVに出力される(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $a = Request::factory()->create(['office_id' => $officeA->id]);
        $b = Request::factory()->create(['office_id' => $officeB->id]);

        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        $content = $this->streamedContent($this->get(route('requests.export')));

        $this->assertStringContainsString($a->reception_number, $content);
        $this->assertStringContainsString($b->reception_number, $content);
    }

    public function test_検索条件でCSVの出力対象が絞り込まれる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office);
        $road = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $river = Request::factory()->create(['office_id' => $office->id, 'department' => 'river']);

        $content = $this->streamedContent(
            $this->get(route('requests.export', ['department' => ['road']]))
        );

        $this->assertStringContainsString($road->reception_number, $content);
        $this->assertStringNotContainsString($river->reception_number, $content);
    }

    public function test_enum値は日本語ラベルで出力される(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office);
        Request::factory()->create([
            'office_id' => $office->id,
            'department' => 'road',
            'response_status' => 'completed',
            'response_completed_date' => '2026-07-27',
            'urgency' => 'high',
        ]);

        $content = $this->streamedContent($this->get(route('requests.export')));

        $this->assertStringContainsString('道路', $content);
        $this->assertStringContainsString('対応完了', $content);
        $this->assertStringContainsString('高', $content);
    }

    public function test_不正な検索条件はバリデーションエラーになる(): void
    {
        $this->actingAsStaff();

        $this->get(route('requests.export', ['department' => ['invalid_dept']]))
            ->assertSessionHasErrors('department.0');
    }
}
