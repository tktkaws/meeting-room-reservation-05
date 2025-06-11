# 会議室予約システム

## 概要
PHPとSQLiteを使用した会議室予約管理システムです。ユーザー認証、予約管理、繰り返し予約機能を備えています。

## 技術仕様
- **フロントエンド**: HTML5, CSS3, Vanilla JavaScript (ES6+)
- **バックエンド**: PHP 8.0+
- **データベース**: SQLite
- **アーキテクチャ**: RESTful API設計

## 機能一覧

### 認証機能
- [x] ユーザー登録
- [x] ログイン/ログアウト
- [x] セッション管理
- [x] 権限管理（一般ユーザー/管理者）

### 予約機能
- [x] カレンダー表示での空き状況確認
- [x] 新規予約作成
- [x] 予約の編集・削除
- [x] 繰り返し予約（毎日/毎週/毎月）
- [x] 重複チェック機能

### セキュリティ機能
- [x] SQLインジェクション対策
- [x] XSS（クロスサイトスクリプティング）対策
- [x] CSRF（クロスサイトリクエストフォージェリ）対策
- [x] パスワードハッシュ化
- [x] レート制限
- [x] 入力値検証・サニタイズ

## セットアップ手順

### 必要環境
- PHP 8.0以上
- Apache/Nginx ウェブサーバー
- SQLite3サポート

### インストール
1. プロジェクトファイルをウェブサーバーのドキュメントルートに配置
2. データベース初期化: `http://localhost/meeting-room-reservation-05/database/init_database.php`
3. （オプション）サンプルデータ挿入: `http://localhost/meeting-room-reservation-05/database/sample_data.php`

### ディレクトリ構造
```
meeting-room-reservation-05/
├── api/                    # APIエンドポイント
│   ├── auth.php           # 認証API
│   ├── config.php         # 設定・共通関数
│   ├── reservations.php   # 予約API
│   └── security.php       # セキュリティAPI
├── css/                   # スタイルシート
│   └── style.css
├── js/                    # JavaScript
│   └── main.js
├── database/              # データベース関連
│   ├── meeting_room.db    # SQLiteデータベースファイル
│   ├── schema.sql         # データベーススキーマ
│   ├── init_database.php  # データベース初期化スクリプト
│   └── sample_data.php    # サンプルデータ挿入
├── auth.html             # 認証ページ
├── index.html            # メインページ
└── README.md             # このファイル
```

## 使用方法

### 初回セットアップ
1. `auth.html` にアクセス
2. 新規登録またはテストアカウントでログイン

### テストアカウント
- **管理者**: `admin@example.com` / `admin123`
- **一般ユーザー**: `user@example.com` / `user123`

### 予約作成
1. カレンダーの日付をクリック
2. 予約フォームに情報を入力
3. 繰り返し予約の場合は「繰り返し予約」にチェック
4. 保存ボタンをクリック

### 予約編集・削除
1. カレンダーの予約アイテムをクリック
2. 編集フォームで変更または削除ボタンをクリック

## データベース設計

### テーブル構造

#### users（ユーザー）
- `id`: 主キー
- `name`: ユーザー名
- `email`: メールアドレス（一意）
- `password`: ハッシュ化されたパスワード
- `role`: 権限（user/admin）
- `department`: 部署
- `created_at`, `updated_at`: タイムスタンプ

#### reservations（予約）
- `id`: 主キー
- `user_id`: ユーザーID（外部キー）
- `title`: 予約タイトル
- `description`: 説明
- `date`: 予約日
- `start_datetime`, `end_datetime`: 開始・終了日時
- `group_id`: 繰り返しグループID（外部キー）
- `created_at`, `updated_at`: タイムスタンプ

#### reservation_groups（繰り返し予約グループ）
- `id`: 主キー
- `title`: グループタイトル
- `description`: 説明
- `user_id`: ユーザーID（外部キー）
- `repeat_type`: 繰り返しタイプ（daily/weekly/monthly）
- `repeat_interval`: 繰り返し間隔
- `start_date`, `end_date`: 開始・終了日
- `days_of_week`: 曜日指定
- `created_at`: タイムスタンプ

#### reservation_group_relations（予約とグループの関連）
- `id`: 主キー
- `reserve_id`: 予約ID（外部キー）
- `group_id`: グループID（外部キー）
- `created_at`: タイムスタンプ

## API仕様

### 認証 (`/api/auth.php`)
- `POST` - ログイン（action=login）
- `POST` - ユーザー登録（action=register）
- `POST` - ログアウト（action=logout）
- `GET` - 現在のユーザー情報取得

### 予約 (`/api/reservations.php`)
- `GET` - 予約一覧取得
- `POST` - 新規予約作成
- `PUT` - 予約更新
- `DELETE` - 予約削除

### セキュリティ (`/api/security.php`)
- `GET` - CSRFトークン取得（action=csrf_token）
- `GET` - セキュリティ情報取得（action=security_info）

## セキュリティ対策

### 実装済み対策
1. **SQLインジェクション**: プリペアドステートメント使用
2. **XSS**: 出力時のエスケープ処理
3. **CSRF**: トークンベースの保護
4. **パスワード**: bcryptハッシュ化
5. **セッション**: 安全な設定とID再生成
6. **レート制限**: API呼び出し頻度制限
7. **入力検証**: 型チェックと長さ制限

### 推奨追加対策
- HTTPS通信の強制
- セッションタイムアウト設定
- ログ監視システム
- ファイアウォール設定

## トラブルシューティング

### よくある問題
1. **データベース接続エラー**: ファイルパーミッション確認
2. **ログインできない**: ブラウザのクッキー設定確認
3. **予約が表示されない**: データベース初期化確認

### ログ確認
- PHPエラーログ: サーバーの設定に依存
- アプリケーションログ: `error_log()` 関数で記録

## 開発情報

### 今後の改善案
- 週表示・日表示の実装
- メール通知機能
- 予約承認フロー
- ファイル添付機能
- API レスポンス時間の最適化

### 貢献方法
1. イシューの報告
2. プルリクエストの作成
3. ドキュメントの改善

## ライセンス
このプロジェクトはMITライセンスの下で公開されています。

## 更新履歴
- v1.0.0: 初回リリース
  - 基本的な予約機能
  - ユーザー認証
  - 繰り返し予約
  - セキュリティ対策