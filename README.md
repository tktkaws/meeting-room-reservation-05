# 会議室予約システム - 初学者向け解説

## 概要
このシステムは、PHPとSQLiteを使って作られた会議室予約管理システムです。ユーザー認証、予約の作成・編集・削除、繰り返し予約などの機能を提供します。

## ファイル構成
```
meeting-room-reservation-05/
├── index.html              # メインページ（カレンダー表示）
├── auth.html              # ログイン・登録ページ
├── css/style.css          # スタイルシート
├── js/main.js            # フロントエンドJavaScript
├── api/                  # バックエンドAPI
│   ├── config.php        # 設定とヘルパー関数
│   ├── auth.php          # 認証API
│   ├── reservations.php  # 予約CRUD API
│   ├── reservation_detail.php # 予約詳細API
│   └── group_edit.php    # グループ編集API
└── database/
    ├── schema.sql        # データベース構造
    └── init_database.php # データベース初期化
```

## セクション1: ログイン機能の仕組み

### 1.1 認証の流れ
1. **ログインページ表示** (`auth.html`)
   - ユーザーがメールアドレスとパスワードを入力
   - JavaScriptがフォーム送信をキャッチ

2. **認証API呼び出し** (`api/auth.php`)
   ```javascript
   // js/main.js - ログイン処理
   async function handleLogin() {
       const formData = new FormData(document.getElementById('loginForm'));
       formData.append('action', 'login');
       
       const response = await fetch('api/auth.php', {
           method: 'POST',
           body: formData
       });
   }
   ```

3. **サーバー側認証処理** (`api/auth.php`)
   ```php
   // パスワードのハッシュ化確認
   if (password_verify($password, $user['password'])) {
       $_SESSION['user_id'] = $user['id'];
       $_SESSION['role'] = $user['role'];
       // ログイン成功
   }
   ```

### 1.2 セッション管理
- PHPの`$_SESSION`を使用してログイン状態を保持
- `requireAuth()`関数で各APIの認証チェック
- ログアウト時にセッションを破棄

### 1.3 セキュリティ対策
- パスワードは`password_hash()`でハッシュ化して保存
- CSRF対策でセッショントークンを使用
- SQLインジェクション対策でプリペアードステートメントを使用

## セクション2: 新規予約機能の仕組み

### 2.1 予約作成の流れ
1. **予約フォーム表示**
   ```javascript
   // 新規予約ボタンクリック時
   function openNewReservationModal(selectedDate = null) {
       const modal = document.getElementById('reservation-modal');
       // フォームをリセットして表示
       modal.style.display = 'flex';
   }
   ```

2. **フォーム送信処理**
   ```javascript
   async function handleReservationSubmit(e) {
       e.preventDefault();
       const formData = new FormData(form);
       
       const data = {
           title: formData.get('title'),
           description: formData.get('description'),
           date: formData.get('date'),
           start_time: formData.get('start_time'),
           end_time: formData.get('end_time'),
           is_recurring: formData.get('is_recurring') === 'on'
       };
   }
   ```

3. **サーバー側処理** (`api/reservations.php`)
   ```php
   function handleCreateReservation() {
       // 入力検証
       validateInput($title, 'string', 100);
       
       // 時間重複チェック
       if (!checkTimeConflict($date, $startDatetime, $endDatetime)) {
           throw new Exception('この時間帯は既に予約されています');
       }
       
       // データベースに保存
       $stmt = $db->prepare("INSERT INTO reservations ...");
   }
   ```

### 2.2 入力検証
- **フロントエンド**: HTMLの`required`属性とJavaScriptでの基本チェック
- **バックエンド**: `validateInput()`関数で厳密な検証
  ```php
  function validateInput($value, $type, $maxLength = null) {
      switch ($type) {
          case 'string':
              return is_string($value) && 
                     ($maxLength === null || mb_strlen($value) <= $maxLength);
          case 'time':
              return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value);
      }
  }
  ```

### 2.3 時間重複チェック
```php
function checkTimeConflict($date, $startDatetime, $endDatetime, $excludeId = null) {
    $sql = "SELECT id FROM reservations 
            WHERE date = ? AND id != ? AND (
                (start_datetime < ? AND end_datetime > ?) OR
                (start_datetime < ? AND end_datetime > ?) OR
                (start_datetime >= ? AND start_datetime < ?)
            )";
}
```

## セクション3: 繰り返し予約機能の仕組み

### 3.1 データベース設計
```sql
-- 繰り返し予約グループ
CREATE TABLE reservation_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    repeat_type TEXT NOT NULL,  -- 'daily', 'weekly', 'monthly'
    user_id INTEGER NOT NULL
);

-- 個別の予約
CREATE TABLE reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    date DATE NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    group_id INTEGER,  -- 繰り返し予約のグループID
    FOREIGN KEY (group_id) REFERENCES reservation_groups(id)
);
```

### 3.2 繰り返し予約作成処理
```php
function createRecurringReservations($data) {
    // 1. グループを作成
    $stmt = $db->prepare("INSERT INTO reservation_groups ...");
    $groupId = $db->lastInsertId();
    
    // 2. 各日付の予約を作成
    $currentDate = new DateTime($startDate);
    $endDate = new DateTime($repeatEndDate);
    
    while ($currentDate <= $endDate) {
        createSingleReservation($data, $groupId, $currentDate);
        
        // 次の日付を計算
        switch ($repeatType) {
            case 'daily': $currentDate->add(new DateInterval('P1D')); break;
            case 'weekly': $currentDate->add(new DateInterval('P7D')); break;
            case 'monthly': $currentDate->add(new DateInterval('P1M')); break;
        }
    }
}
```

## セクション4: 予約編集機能の仕組み

### 4.1 編集モードの種類
1. **単発予約編集**: 個別の予約のみを編集
2. **個別編集**: 繰り返し予約から1つを取り出して編集
3. **グループ編集**: 繰り返し予約全体を一括編集

### 4.2 予約詳細表示
```javascript
async function showReservationDetail(reservationId) {
    // 予約詳細を取得
    const response = await fetch(`api/reservation_detail.php?id=${reservationId}`);
    const result = await response.json();
    
    // 編集ボタンを動的に生成
    if (reservation.group_id) {
        // 繰り返し予約の場合は2つのボタン
        detailActions.innerHTML = `
            <button onclick="editSingleReservation(${reservationId})">この予約のみ編集</button>
            <button onclick="editGroupReservations(${reservation.group_id})">全ての繰り返し予約を編集</button>
        `;
    } else {
        // 単発予約の場合は1つのボタン
        detailActions.innerHTML = `
            <button onclick="editSingleReservation(${reservationId})">編集</button>
        `;
    }
}
```

### 4.3 グループ一括編集
```javascript
async function handleGroupEditSubmit(e) {
    const data = {
        group_id: parseInt(groupId),
        title: formData.get('title'),
        description: formData.get('description'),
        bulk_time_update: {
            start_time: newStartTime,
            end_time: newEndTime
        }
    };
    
    // APIに送信
    const response = await fetch('api/group_edit.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
}
```

## セクション5: カレンダー表示機能の仕組み

### 5.1 カレンダーレンダリング
```javascript
function renderMonthView(container) {
    const monthStart = getMonthStart(currentDate);
    const monthEnd = getMonthEnd(currentDate);
    
    // 7×6のグリッドを作成
    let html = '<div class="calendar-grid">';
    
    // 各日付セルを生成
    let currentDay = new Date(calendarStart);
    while (currentDay <= calendarEnd) {
        const dayReservations = getDayReservations(currentDay);
        html += `
            <div class="calendar-day" data-date="${formatDate(currentDay)}">
                <div class="day-number">${currentDay.getDate()}</div>
                <div class="reservations">
                    ${dayReservations.map(res => `
                        <div class="reservation-item" onclick="showReservationDetail(${res.id})">
                            ${res.title}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        currentDay.setDate(currentDay.getDate() + 1);
    }
}
```

### 5.2 予約データの取得と表示
```javascript
async function loadReservations() {
    const startDate = formatDate(getMonthStart(currentDate));
    const endDate = formatDate(getMonthEnd(currentDate));
    
    const response = await fetch(`api/reservations.php?start_date=${startDate}&end_date=${endDate}`);
    const result = await response.json();
    
    reservations = result.reservations || [];
}
```

## セクション6: データベース操作の基本

### 6.1 接続と設定
```php
function getDatabase() {
    $dbFile = __DIR__ . '/../database/meeting_room.db';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
```

### 6.2 トランザクション処理
```php
try {
    $db->beginTransaction();
    
    // 複数のSQL操作
    $stmt1 = $db->prepare("INSERT INTO reservation_groups ...");
    $stmt2 = $db->prepare("INSERT INTO reservations ...");
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

## セクション7: エラーハンドリングとデバッグ

### 7.1 エラーレスポンス
```php
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
```

### 7.2 フロントエンドエラー処理
```javascript
try {
    const response = await fetch('api/reservations.php', {
        method: 'POST',
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
        showMessage('予約を作成しました', 'success');
    } else {
        showMessage(result.error || '予約の作成に失敗しました', 'error');
    }
} catch (error) {
    console.error('APIエラー:', error);
    showMessage('通信エラーが発生しました', 'error');
}
```

## セクション8: セキュリティ考慮事項

### 8.1 入力値のサニタイズ
```php
function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}
```

### 8.2 権限チェック
```php
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['error' => '認証が必要です'], 401);
    }
}

// 編集権限チェック
if ($reservation['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
    sendJsonResponse(['error' => '編集権限がありません'], 403);
}
```

## まとめ
このシステムは以下の要素で構成されています：
- **フロントエンド**: HTML/CSS/JavaScript（Vanilla JS）
- **バックエンド**: PHP（RESTful API）
- **データベース**: SQLite
- **認証**: セッションベース
- **セキュリティ**: パスワードハッシュ化、CSRF対策、入力検証

各機能は独立したAPIエンドポイントとして設計されており、フロントエンドとバックエンドが明確に分離された構造になっています。

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