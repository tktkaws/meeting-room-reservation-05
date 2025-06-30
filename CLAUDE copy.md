# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## プロジェクト概要
PHP + SQLite + JavaScript で構築された会議室予約管理システムです。RESTful API設計でフロントエンドとバックエンドが分離されています。

## アーキテクチャ

### データフロー
1. **フロントエンド（Vanilla JS）** → **RESTful API（PHP）** → **SQLite DB**
2. 認証はセッションベース、全APIエンドポイントで認証チェック実装
3. 予約データはJSON形式でAPI経由で送受信

### 主要コンポーネント
- **認証システム**: `api/auth.php` でログイン・登録・セッション管理
- **予約管理**: `api/reservations.php` で予約のCRUD操作
- **繰り返し予約**: `reservation_groups`テーブルでグループ管理、個別編集可能
- **カレンダー表示**: 平日のみ（月〜金）表示、期間内全予約を動的取得

### データベース設計
- **users**: ユーザー情報（認証・権限）
- **reservations**: 個別予約（group_idで繰り返しと紐付け）
- **reservation_groups**: 繰り返し予約グループ
- **reservation_group_relations**: 予約とグループの関連

## 開発コマンド

### データベース初期化
```bash
# ブラウザでアクセス
http://localhost/meeting-room-reservation-05/database/init_database.php

# サンプルデータ挿入（オプション）
http://localhost/meeting-room-reservation-05/database/sample_data.php
```

### テストアカウント
- 管理者: `admin@example.com` / `admin123`
- 一般ユーザー: `user@example.com` / `user123`

### API エンドポイント
- `POST api/auth.php` - 認証（action=login/register/logout）
- `GET/POST/PUT/DELETE api/reservations.php` - 予約CRUD
- `GET api/reservation_detail.php` - 予約詳細取得
- `PUT api/group_edit.php` - 繰り返し予約グループ編集
- `GET api/security.php` - CSRFトークン取得

## 開発ルール

### 機能開発時の必須作業
新機能を実装したら `.claude/` フォルダに解説MDファイルを作成してください。

### コード規約
- PHP: プリペアードステートメント必須（SQLインジェクション対策）
- JavaScript: Vanilla JS使用、ES6+構文
- API: JSON形式、適切なHTTPステータスコード返却
- セキュリティ: `sendJsonResponse()`, `requireAuth()` 等の共通関数を使用

### カレンダー機能特記事項
- 平日のみ表示（`getWeekdayStart()`, `getWeekdayEnd()` 関数）
- 期間跨ぎ対応（例: 6月は6/2〜7/4を表示）
- 予約データは表示期間の全日程を一括取得

## TODOリスト
- [x] カレンダー表示の修正（平日のみ）
- [x] 予約一覧はログイン不要で閲覧可能
- [x] フォーム　15分単位　開始時間を変更したら終了時間を1時間後にリアルタイムで変換
- [x] 週間表示
- [x] リスト表示
- [x] 個人のページ　メールの設定
- [x] メール通知機能実装
- [x] 予約CRUD連動メール機能削除（ユーザーテーブルのemail_notification_typeカラムは保持）
- [ ] 

### 重要な技術的制約
- SQLiteファイルのパーミッション確認が必要
- セッション管理は`api/config.php`で統一
- 繰り返し予約の編集は個別/グループ選択式
- 時間重複チェックは`checkTimeConflict()`関数で実装済み