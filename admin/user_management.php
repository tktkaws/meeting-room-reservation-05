<?php
// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// データベース設定（相対パスを修正）
define('DB_PATH', __DIR__ . '/../database/meeting_room.db');

// データベース接続関数
function getDatabase() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('データベース接続エラー: ' . $e->getMessage());
    }
}

// 現在のユーザー数を取得
function getUserCount() {
    try {
        $pdo = getDatabase();
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        return $result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

$userCount = getUserCount();
$message = '';
$messageType = '';

// ユーザーテーブル初期化処理
if (isset($_POST['init_users'])) {
    try {
        $pdo = getDatabase();
        
        // 外部キー制約を一時的に無効化
        $pdo->exec("PRAGMA foreign_keys = OFF");
        
        // 関連テーブルの予約データを削除
        $pdo->exec("DELETE FROM reservations");
        $pdo->exec("DELETE FROM reservation_groups");
        
        // ユーザーテーブルを削除して再作成
        $pdo->exec("DROP TABLE IF EXISTS users");
        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user',
                department INTEGER DEFAULT 1,
                email_notification_type INTEGER DEFAULT 2,
                department_theme_colors TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 外部キー制約を再有効化
        $pdo->exec("PRAGMA foreign_keys = ON");
        
        $message = 'ユーザーテーブルを初期化しました。';
        $messageType = 'success';
        $userCount = 0;
        
    } catch (Exception $e) {
        $message = 'ユーザーテーブルの初期化に失敗しました: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// CSV取り込み処理
if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
    try {
        $csvFile = $_FILES['csv_file'];
        
        if ($csvFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ファイルのアップロードに失敗しました。エラーコード: ' . $csvFile['error']);
        }
        
        $handle = fopen($csvFile['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('CSVファイルを開けませんでした。');
        }
        
        // BOM (Byte Order Mark) を除去
        $firstLine = fgets($handle);
        if (substr($firstLine, 0, 3) === "\xEF\xBB\xBF") {
            // BOMがある場合は除去してファイルポインタをリセット
            fclose($handle);
            $content = file_get_contents($csvFile['tmp_name']);
            $content = substr($content, 3); // BOMを除去
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_nobom');
            file_put_contents($tempFile, $content);
            $handle = fopen($tempFile, 'r');
        } else {
            // BOMがない場合はファイルポインタを先頭に戻す
            rewind($handle);
        }
        
        $pdo = getDatabase();
        $pdo->beginTransaction();
        
        // 利用可能な部署IDを取得
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY id");
        $availableDepartments = [];
        while ($row = $stmt->fetch()) {
            $availableDepartments[$row['id']] = $row['name'];
        }
        
        $insertedCount = 0;
        $errors = [];
        $lineNumber = 0;
        $debugInfo = [];
        
        // ヘッダー行をスキップ
        $header = fgetcsv($handle);
        $debugInfo[] = "ヘッダー行: " . (is_array($header) ? implode(' | ', $header) : 'null');
        $debugInfo[] = "ヘッダー行の列数: " . (is_array($header) ? count($header) : 0);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $lineNumber++;
            $debugInfo[] = "行 {$lineNumber}: " . implode(' | ', $data);
            
            if (count($data) < 3) {
                $errors[] = "行 {$lineNumber}: データが不足しています (取得した列数: " . count($data) . ")";
                continue;
            }
            
            $name = trim($data[0]);
            $email = trim($data[1]);
            $departmentId = trim($data[2]);
            $role = isset($data[3]) ? trim($data[3]) : 'user';
            
            $debugInfo[] = "行 {$lineNumber} 処理中: 名前='{$name}', メール='{$email}', 部署ID='{$departmentId}', 役割='{$role}'";
            
            if (empty($name) || empty($email)) {
                $errors[] = "行 {$lineNumber}: 名前またはメールアドレスが空です (名前: '{$name}', メール: '{$email}')";
                continue;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "行 {$lineNumber}: メールアドレスの形式が正しくありません ('{$email}')";
                continue;
            }
            
            // 部署IDの検証
            if (!is_numeric($departmentId)) {
                $errors[] = "行 {$lineNumber}: 部署IDは数値である必要があります ('{$departmentId}')";
                continue;
            }
            
            $departmentId = (int)$departmentId;
            if (!isset($availableDepartments[$departmentId])) {
                $errors[] = "行 {$lineNumber}: 部署ID '{$departmentId}' が見つかりません。利用可能な部署ID: " . implode(', ', array_keys($availableDepartments));
                continue;
            }
            
            // デフォルトパスワードを生成（メールアドレスのローカル部分 + "123"）
            $defaultPassword = explode('@', $email)[0] . '123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, department, email_notification_type) 
                    VALUES (?, ?, ?, ?, ?, 2)
                ");
                $stmt->execute([$name, $email, $hashedPassword, $role, $departmentId]);
                $insertedCount++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // UNIQUE constraint violation
                    $errors[] = "行 {$lineNumber}: メールアドレス {$email} は既に存在しています";
                } else {
                    $errors[] = "行 {$lineNumber}: データベースエラー - " . $e->getMessage();
                }
            }
        }
        
        fclose($handle);
        
        // 一時ファイルがあれば削除
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        if ($insertedCount > 0) {
            $pdo->commit();
            $message = "{$insertedCount} 件のユーザーを取り込みました。";
            if (!empty($errors)) {
                $message .= "\n\n以下のエラーがありました:\n" . implode("\n", $errors);
            }
            $messageType = 'success';
            $userCount = getUserCount();
        } else {
            $pdo->rollback();
            $message = "取り込み可能なユーザーがありませんでした。\n\n";
            $message .= "デバッグ情報:\n" . implode("\n", $debugInfo) . "\n\n";
            $message .= "エラー詳細:\n" . (empty($errors) ? "エラーなし" : implode("\n", $errors));
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollback();
        }
        $message = 'CSV取り込みに失敗しました: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理 - 会議室予約システム</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .file-upload {
            margin-bottom: 1rem;
        }

        .file-upload input[type="file"] {
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 6px;
            width: 100%;
            margin-bottom: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .csv-format {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }

        .csv-format h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .csv-format code {
            background-color: #e9ecef;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .user-count {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ユーザー管理</h1>
            <p>ユーザーテーブルの初期化とCSVからのユーザー取り込み</p>
        </div>

        <div class="user-count">
            現在のユーザー数: <?php echo $userCount; ?> 人
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>ユーザーテーブル初期化</h2>
            <p>⚠️ <strong>注意:</strong> この操作により、すべてのユーザーデータと関連する予約データが削除されます。</p>
            <div class="action-buttons">
                <form method="POST" onsubmit="return confirm('本当にユーザーテーブルを初期化しますか？すべてのユーザーと予約データが削除されます。')">
                    <button type="submit" name="init_users" class="btn btn-danger">ユーザーテーブル初期化</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>CSVからユーザー取り込み</h2>
            
            <div class="csv-format">
                <h3>CSVファイル形式</h3>
                <p>以下の形式でCSVファイルを作成してください：</p>
                <p><code>名前,メールアドレス,部署ID,役割</code></p>
                <p><strong>例:</strong></p>
                <p><code>田中太郎,tanaka@example.com,1,user</code></p>
                <p><code>佐藤花子,sato@example.com,2,admin</code></p>
                <p><strong>利用可能な部署ID:</strong></p>
                <ul>
                    <li>1: 取締役</li>
                    <li>2: 総務管理部</li>
                    <li>3: 営業開発推進部</li>
                    <li>4: 制作部</li>
                </ul>
                <p><strong>注意:</strong></p>
                <ul>
                    <li>1行目はヘッダー行として扱われ、スキップされます</li>
                    <li>役割は「user」または「admin」を指定（省略時は「user」）</li>
                    <li>部署IDは上記のいずれかを正確に入力してください</li>
                    <li>パスワードは「メールアドレスのローカル部分 + 123」で自動生成されます</li>
                    <li>例: tanaka@example.com → パスワード: tanaka123</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="file-upload">
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="import_csv" class="btn btn-success">CSVファイルを取り込む</button>
                </div>
            </form>
        </div>

        <a href="index.html" class="back-link">← メインページに戻る</a>
    </div>
</body>
</html>