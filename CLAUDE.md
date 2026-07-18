# 開発ルール（Claude Code 用）

このファイルは Claude Code が必ず遵守するルールを定義します。
プロジェクトの詳細（概要・機能要件）は [docs/requirements.md](docs/requirements.md) を参照してください。

## ⚠️ 作業開始前チェックリスト（必須）

**いかなる実装・ファイル編集も、以下をすべて確認してから開始すること。**
1つでも未完了なら、作業を止めてこのリストを満たしてから進めること。

- [ ] **Issue を作成したか？** → `gh issue create` で Issue を作り、番号を確認する
- [ ] **feature/fix/docs/chore ブランチを切ったか？** → `git checkout -b <prefix>/<Issue番号>-<内容>`
- [ ] **main ブランチに直接いないか？** → `git branch` で現在のブランチを確認する
- [ ] **コミットメッセージは Conventional Commits 形式か？** → `feat:` `fix:` `chore:` 等

> このチェックリストはルールの抜粋です。詳細は各セクションを参照してください。

---

## 1. ブランチ命名規則

ブランチを作成する際は、必ず以下のプレフィックスを使用してください。

| プレフィックス | 用途 | 例 |
|---|---|---|
| `feature/` | 新機能の実装 | `feature/12-add-request-map` |
| `fix/` | バグ修正 | `fix/15-search-filter-error` |
| `docs/` | ドキュメント変更のみ | `docs/1-requirements` |
| `chore/` | 設定変更・依存関係更新など | `chore/update-dependencies` |

- プレフィックスの後は英小文字・ハイフン区切りで記述する（スペース・アンダースコア禁止）
- Issue番号を含める形式を推奨：`feature/<Issue番号>-<作業内容>`

---

## 2. Issue 作成ルール（必須）

**ブランチを作成する前に、必ず GitHub Issue を作成してください。**

手順：
1. `gh issue create` コマンド、または GitHub Web UI で Issue を作成する
2. Issue 番号を確認する
3. Issue 番号をブランチ名に含める：`feature/12-add-request-map`
4. ブランチを作成して作業を開始する

```bash
# Issue作成コマンド例
gh issue create --title "feat: 要望箇所の地図表示機能を追加" --label "enhancement"
```

---

## 3. コミットメッセージ規則（Conventional Commits）

以下の形式に従ってコミットメッセージを書いてください。

```
<type>: <日本語または英語で概要を記述>
```

### type 一覧

| type | 用途 |
|---|---|
| `feat` | 新機能追加 |
| `fix` | バグ修正 |
| `docs` | ドキュメントのみの変更 |
| `style` | フォーマット変更（動作に影響しない） |
| `refactor` | リファクタリング（機能変更なし） |
| `test` | テストコードの追加・修正 |
| `chore` | ビルド・設定ファイルの変更 |

### 例

```
feat: 要望箇所の地図表示機能を追加
fix: 検索条件のAND/OR切り替えの不具合を修正
docs: 要件定義書を作成
chore: 依存パッケージを更新
```

---

## 4. プルリクエスト（PR）ルール

- **main への直接プッシュは禁止**
- 必ず `feature/` `fix/` `docs/` `chore/` ブランチを作成し、PR を通じて main にマージする
- PR タイトルはコミットメッセージと同様に Conventional Commits 形式にする

```bash
# PR作成コマンド例
gh pr create --title "docs: 要件定義書を作成" --base main
```

---

## 5. 作業の流れ（必ず守る手順）

```
1. gh issue create でIssueを作成し、Issue番号を確認する
2. git checkout -b feature/<Issue番号>-<作業内容>
3. 実装・コミット（Conventional Commits形式）
4. git push origin feature/<ブランチ名>
5. gh pr create でPRを作成する
6. PRをマージする
7. git branch -d feature/<ブランチ名> でローカルブランチを削除する
```

---

## 6. 扱うデータに関する注意（重要）

このアプリは要望者に関する情報として「区分（個人／自治会／市町村／議員／匿名／職員パトロール／その他）」「要望者（氏名・団体名等）」を扱う。氏名・住所・連絡先・性別・年齢等の詳細な個人情報は原則保持しない方針とする（詳細は [docs/requirements.md](docs/requirements.md) 参照）。

- サンプルデータ・テストデータには実在の個人・団体名を使用しないこと
- ログや一時ファイルに要望者情報を出力しないよう注意すること
- 技術スタック・環境構成は本ファイルではなく、要件定義書・基本設計書・READMEに記載する
