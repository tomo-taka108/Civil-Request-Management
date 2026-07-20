# 画面設計書

最終更新日: 2026-07-20

[要件定義書](requirements.md) 5章の画面イメージ、および `mockup/` の画面プロトタイプをもとに、
Laravel（Blade）でのルーティング・コントローラ・権限制御を設計する。
テーブル構成は [データベース設計書](database-design.md) を参照。変更履歴は`git log docs/screen-design.md`を参照。

---

## 1. 画面一覧

| # | 画面名 | 対応モックアップ | 権限 |
|---|---|---|---|
| 1 | ログイン | `login.html` | 未認証ユーザー |
| 2 | パスワード変更 | `password-change.html` | 認証済み全ユーザー |
| 3 | 案件一覧・検索 | `list.html` | 認証済み全ユーザー |
| 4 | 案件詳細 | `detail.html` | 認証済み全ユーザー |
| 5 | 案件新規登録 | `register.html` | 認証済み全ユーザー |
| 6 | 案件編集・削除 | `edit.html` | 対応部署が一致する担当者のみ |
| 7 | 地図表示 | `map.html` | 認証済み全ユーザー |
| 8 | ユーザー一覧 | `user-list.html` | システム管理者のみ |
| 9 | ユーザー登録 | `user-register.html` | システム管理者のみ |
| 10 | ユーザー編集・無効化 | `user-edit.html` | システム管理者のみ |

> 全10画面のモックアップ作成が完了。#7（地図表示）・#8（ユーザー一覧）・#10（ユーザー編集・無効化）は、機能要件（2.4・2.5章）から存在が必須と判明していたが未作成だったもので、本改訂までに順次作成した。

---

## 2. ルーティング設計

Laravel標準のリソースルーティングを基本とし、業務要件に合わせて命名する。
全ルートは `auth` ミドルウェア配下（ログイン画面を除く）。

```php
// 認証
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    // パスワード変更（初回強制変更を含む）
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password/change', [PasswordController::class, 'update'])->name('password.update');

    // 案件（要望・苦情・異常箇所）
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{request}', [RequestController::class, 'show'])->name('requests.show');
    Route::get('/requests/{request}/edit', [RequestController::class, 'edit'])
        ->middleware('can:update,request')->name('requests.edit');
    Route::put('/requests/{request}', [RequestController::class, 'update'])
        ->middleware('can:update,request')->name('requests.update');
    Route::delete('/requests/{request}', [RequestController::class, 'destroy'])
        ->middleware('can:delete,request')->name('requests.destroy');
    Route::get('/requests-export', [RequestController::class, 'exportCsv'])->name('requests.export');

    // 地図表示
    Route::get('/map', [MapController::class, 'index'])->name('map.index');
    Route::get('/map/pins', [MapController::class, 'pins'])->name('map.pins'); // 非同期取得用（GeoJSON等）

    // ユーザー管理（システム管理者限定）
    Route::middleware('can:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    });
});
```

**設計メモ**
- 案件のURLは `/requests` に統一（受付番号ではなく内部ID `{request}` でルーティングし、表示上は受付番号を使う）
- CSV出力は一覧検索と同じ条件を引き継ぐため、`GET /requests-export` にクエリパラメータで検索条件を渡す設計とする
- 地図のピン取得はページ本体（`/map`）とは別に非同期エンドポイント（`/map/pins`）を用意し、検索結果のフィルタ表示（要件2.4）に対応する
- 対応部署・対応状況・緊急性は複数選択検索（要件定義書2.3）に対応するため、クエリパラメータは配列形式（例：`?department[]=road&department[]=river`）とし、コントローラ側で`whereIn`により絞り込む。`/map/pins`も同じクエリパラメータ形式を共有し、一覧画面と地図画面で検索条件を揃える

---

## 3. 権限制御方針

要件定義書 1.3・4.1 に基づき、Laravelの標準機能で以下のように実装する。

### 3.1 認証・事務所スコープ

- Laravel標準の`auth`ミドルウェアでログイン必須を担保
- 一般職員（`role === 'staff'`）はログイン中ユーザーの`office_id`を基準に、**全てのクエリに事務所スコープを適用する**
  - `Request`モデルに `office_id` のグローバルスコープ（`BootedByOffice`等）を実装し、`Auth::user()->office_id` で自動フィルタする方式を採用
  - コントローラ側で個別に `where('office_id', ...)` を書き漏らすリスクを避けるため、モデル層で一元化する
- **システム管理者（`role === 'admin'`）は`office_id`を持たない（DB設計書2.2参照）ため、上記グローバルスコープの対象外とする**。管理者ログイン時はスコープを適用せず、全事務所の`requests`を検索・取得できるようにする
- 一般職員が他事務所のレコードIDを直接URLで指定した場合（例：`/requests/999`が他事務所の案件）は404を返す（グローバルスコープにより取得自体ができないため自然に404になる）。管理者はこの制限を受けない

### 3.2 編集・削除権限（対応部署）

要件定義書1.3「編集・削除は案件の対応部署に応じた担当者のみ可能」に対応。

- Laravel の**Policy**（`RequestPolicy`）で制御する
  ```php
  public function update(User $user, Request $request): bool
  {
      if ($user->role === 'admin') {
          return true;
      }

      return $user->department === $request->department;
  }

  public function delete(User $user, Request $request): bool
  {
      return $this->update($user, $request);
  }
  ```
- 担当部署・対応部署はいずれも道路／河川／砂防の3種類のみ（「その他」区分は廃止。要件定義書1.3参照）のため、一般職員は`department`の単純一致比較のみで判定できる
- **システム管理者は担当部署によらず全事務所の案件を編集・削除できる**（要件定義書1.3・4.1：個人情報の混入や不適切な記載の是正のため）
- 新規登録（`store`）は担当部署を問わないため、Policyの`create`は常に許可（全認証済みユーザー）
- 閲覧（`show`・`index`）も担当部署を問わないため、一般職員は事務所スコープのみで制御し、Policyでの追加制限はしない。管理者は3.1の通り事務所スコープの対象外

### 3.3 システム管理者限定機能

- ユーザー管理系ルートは `role === 'admin'` を要求する
- Laravelの`Gate::define('admin', fn (User $user) => $user->role === 'admin')` を定義し、ルートミドルウェア`can:admin`で制御する
- **ユーザー管理は事務所横断で行う**：システム管理者は`office_id`を持たない全体管理ロールのため（要件定義書1.3、DB設計書2.2参照）、`UserController`は`office_id`によるスコープを適用せず、全事務所のユーザーを対象に一覧・登録・編集・無効化を行う。ユーザー一覧画面（`/users`）は所属事務所での絞り込みをオプションのフィルタ条件として提供する（`mockup/user-list.html`参照）
- ヘッダーnav（共通レイアウト）の「ユーザー管理」リンクは、ログイン中ユーザーの`role`が`admin`の場合のみ表示する（`@can('admin')`）。システム管理者は案件一覧・地図表示等どの画面からでもユーザー管理に遷移でき、一般職員には表示されない
  - モックアップ（`mockup/list.html`等の案件系5画面）は一般職員ログインを想定しているため「ユーザー管理」リンクは含めていない。`mockup/user-list.html`・`user-edit.html`・`user-register.html`はシステム管理者ログインを想定した別シナリオとして作成しており、これら3画面間は相互に遷移できる

### 3.4 初回ログイン時のパスワード変更強制

- `users.must_change_password = true` のユーザーがログインした場合、`/password/change` 以外へのアクセスをミドルウェア（`ForcePasswordChange`）でリダイレクトする
- パスワード変更成功時に `must_change_password` を `false` に更新する

---

## 4. コントローラ・ビュー対応表

| コントローラ | メソッド | ビュー（Blade） | DB操作 |
|---|---|---|---|
| AuthController | showLoginForm / login / logout | `auth/login.blade.php` | `users`を検索し認証 |
| PasswordController | edit / update | `auth/password.blade.php` | `users.password_hash`, `must_change_password` 更新 |
| RequestController | index | `requests/index.blade.php` | `requests`を条件検索（一般職員は事務所スコープ適用、管理者は全事務所） |
| RequestController | create / store | `requests/create.blade.php` | `requests`へINSERT（採番処理を含む） |
| RequestController | show | `requests/show.blade.php` | `requests`を1件取得 |
| RequestController | edit / update | `requests/edit.blade.php` | `requests`をUPDATE（Policy適用） |
| RequestController | destroy | - | `requests`を論理削除（Policy適用） |
| RequestController | exportCsv | - (CSVダウンロードレスポンス) | `requests`を検索しCSV変換 |
| MapController | index / pins | `map/index.blade.php` | `requests`から緯度経度を持つ案件を条件検索（一般職員は事務所スコープ適用、管理者は全事務所）。`pins`は一覧の各件の緯度経度・受付日時・緊急性等をGeoJSON等で返す |
| UserController | index / create / store / edit / update / deactivate | `users/*.blade.php` | `users`のCRUD（Gate: admin）。事務所スコープなし、全事務所のユーザーが対象。`index`は所属事務所によるフィルタをオプションで受け付ける |

---

## 5. 案件登録時の採番フロー（RequestController::store）

[データベース設計書 3章](database-design.md#3-受付番号の採番方式) の採番方式をコントローラ処理として整理する。

```
1. トランザクション開始
2. ログインユーザーの office_id と当年（reception_year）を取得
3. requests テーブルから (office_id, reception_year) の最大 reception_seq を
   行ロック付きで取得（SELECT ... FOR UPDATE）
4. reception_seq + 1 を新しい連番とし、reception_number を
   "{reception_year}-{reception_seq を4桁ゼロ埋め}" として生成
5. requests に INSERT（registered_by はログインユーザーIDを自動設定）
6. コミット
```

---

## 6. 今後検討すること

- 地図ピン取得APIの詳細設計（GeoJSON等のレスポンス形式、`/map/pins`のフィールド定義）。モックアップは`mockup/map.html`として作成済み、クエリパラメータ方針（2章）は確定済み
- パスワード再発行フロー（`mockup/user-edit.html`の「初期パスワードを再発行する」）の詳細：再発行後の通知方法（画面表示のみ想定、メール等は対象外）、再発行時に`must_change_password`を`true`に戻す仕様の確認
- CSV出力の文字コード（Excelでの文字化け対策としてBOM付きUTF-8を想定）・出力カラムの確定
- バリデーションルール（各項目の文字数上限・必須/任意の詳細）の設計
