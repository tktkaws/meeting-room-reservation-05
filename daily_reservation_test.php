<?php
/**
 * 今日の予約一覧をメールで送信するテストファイル
 * simple_mail_test.phpをベースにして予約データベースと連携
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// データベース設定
define('DB_PATH', __DIR__ . '/database/meeting_room.db');

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

// エラーレポート設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 送信結果とログ
$result = '';
$logs = [];

// PHPの設定情報を取得
$sendmailPath = ini_get('sendmail_path');
$logs[] = "sendmail_path: " . ($sendmailPath ? $sendmailPath : '未設定');

/**
 * メール件名をエンコードする関数
 */
function encodeSubject($subject) {
    // ASCII文字のみの場合はエンコードしない
    if (preg_match('/^[\x20-\x7E]*$/', $subject)) {
        return $subject;
    }
    
    // 文字列が長すぎる場合は短縮
    // $maxLength = 40;
    // if (mb_strlen($subject, 'UTF-8') > $maxLength) {
    //     $subject = mb_substr($subject, 0, $maxLength - 3, 'UTF-8') . '...';
    // }
    
    // Base64エンコード
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

/**
 * 今日の予約一覧を取得
 */
function getTodayReservations() {
    try {
        $db = getDatabase();
        $today = date('Y-m-d');
        
        $sql = "
            SELECT r.*, u.name as user_name, u.department 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.date = ? 
            ORDER BY r.start_datetime ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$today]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $reservations;
        
    } catch (Exception $e) {
        throw new Exception("予約データ取得エラー: " . $e->getMessage());
    }
}

/**
 * 予約一覧をテキスト形式で整形
 */
function formatReservationsText($reservations) {
    $today = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w')] . '）');
    
    $content = "会議室予約 - 本日の予約一覧\n";
    $content .= "========================================\n";
    $content .= "日付: {$today}\n";
    $content .= "取得時刻: " . date('H:i') . "\n";
    $content .= "========================================\n\n";
    
    if (empty($reservations)) {
        $content .= "本日の予約はありません。\n";
    } else {
        $content .= "予約件数: " . count($reservations) . "件\n\n";
        
        foreach ($reservations as $index => $reservation) {
            $num = $index + 1;
            $startTime = date('H:i', strtotime($reservation['start_datetime']));
            $endTime = date('H:i', strtotime($reservation['end_datetime']));
            
            $content .= "【{$num}】 {$startTime}～{$endTime}\n";
            $content .= "　　タイトル: " . $reservation['title'] . "\n";
            $content .= "　　予約者: " . $reservation['user_name'];
            if ($reservation['department']) {
                $content .= " (" . $reservation['department'] . ")";
            }
            $content .= "\n";
            
            if ($reservation['description']) {
                $content .= "　　内容: " . $reservation['description'] . "\n";
            }
            $content .= "\n";
        }
    }
    
    $content .= "========================================\n";
    $content .= "会議室予約システム\n";
    $content .= "URL: http://intra2.jama.co.jp/meeting-room-reservation-05/\n";
    $content .= "送信日時: " . date('Y年n月j日 H:i') . "\n";
    
    return $content;
}

/**
 * 予約一覧をHTML形式で整形
 */
function formatReservationsHTML($reservations) {
    $today = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w')] . '）');
    
    $content = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Yu Gothic", "Hiragino Sans", sans-serif; margin: 20px; }
        .header { background: #007cba; color: white; padding: 15px; border-radius: 5px; }
        .date { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .time { font-size: 14px; opacity: 0.9; }
        .content { margin: 20px 0; }
        .reservation { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
        .title { font-weight: bold; color: #333; font-size: 16px; }
        .meta { color: #666; font-size: 14px; margin: 5px 0; }
        .description { background: #f8f9fa; padding: 8px; border-radius: 3px; margin-top: 8px; }
        .footer { background: #f1f3f4; padding: 15px; border-radius: 5px; text-align: center; }
        .no-reservations { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="date">🏢 会議室予約 - 本日の予約一覧</div>
        <div class="time">日付: ' . $today . ' | 取得時刻: ' . date('H:i') . '</div>
    </div>
    
    <div class="content">';
    
    if (empty($reservations)) {
        $content .= '<div class="no-reservations">📝 本日の予約はありません。</div>';
    } else {
        $content .= '<p><strong>予約件数: ' . count($reservations) . '件</strong></p>';
        
        foreach ($reservations as $index => $reservation) {
            $num = $index + 1;
            $startTime = date('H:i', strtotime($reservation['start_datetime']));
            $endTime = date('H:i', strtotime($reservation['end_datetime']));
            
            $content .= '<div class="reservation">';
            $content .= '<div class="title">【' . $num . '】 ⏰ ' . $startTime . '～' . $endTime . ' - ' . htmlspecialchars($reservation['title']) . '</div>';
            $content .= '<div class="meta">👤 予約者: ' . htmlspecialchars($reservation['user_name']);
            if ($reservation['department']) {
                $content .= ' (' . htmlspecialchars($reservation['department']) . ')';
            }
            $content .= '</div>';
            
            if ($reservation['description']) {
                $content .= '<div class="description">📋 内容: ' . htmlspecialchars($reservation['description']) . '</div>';
            }
            $content .= '</div>';
        }
    }
    
    $content .= '</div>
    
    <div class="footer">
        <p><strong>会議室予約システム</strong></p>
        <p>URL: <a href="http://intra2.jama.co.jp/meeting-room-reservation-05/">http://intra2.jama.co.jp/meeting-room-reservation-05/</a></p>
        <p>送信日時: ' . date('Y年n月j日 H:i') . '</p>
    </div>
</body>
</html>';
    
    return $content;
}

// POSTでメール送信
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? '本日の会議室予約一覧');
    $customMessage = trim($_POST['custom_message'] ?? '');
    $fromEmail = trim($_POST['from_email'] ?? 'meeting-room-reservation@jama.co.jp');
    $fromName = trim($_POST['from_name'] ?? '会議室予約システム');
    $format = $_POST['format'] ?? 'html';
    
    $logs[] = "送信先: $to";
    $logs[] = "件名: $subject";
    $logs[] = "送信者: $fromName <$fromEmail>";
    $logs[] = "形式: " . ($format === 'html' ? 'HTML' : 'テキスト');
    
    if (empty($to)) {
        $result = '<div class="error">送信先メールアドレスを入力してください。</div>';
    } else {
        try {
            // 今日の予約一覧を取得
            $logs[] = "予約データ取得中...";
            $reservations = getTodayReservations();
            $logs[] = "予約件数: " . count($reservations) . "件";
            
            // メッセージを生成
            if ($format === 'html') {
                $contentType = 'text/html; charset=UTF-8';
                $transferEncoding = 'base64';
                $message = formatReservationsHTML($reservations);
                
                // カスタムメッセージがある場合は先頭に追加
                if ($customMessage) {
                    $customMessageHtml = '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
                    $customMessageHtml .= '<p><strong>📢 連絡事項:</strong></p>';
                    $customMessageHtml .= '<p>' . nl2br(htmlspecialchars($customMessage)) . '</p>';
                    $customMessageHtml .= '</div>';
                    $message = str_replace('<div class="content">', '<div class="content">' . $customMessageHtml, $message);
                }
                
                // UTF-8として正しくエンコードされていることを確認
                $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
                $encodedMessage = base64_encode($message);
                
            } else {
                $contentType = 'text/plain; charset=UTF-8';
                $transferEncoding = 'base64';
                $message = formatReservationsText($reservations);
                
                // カスタムメッセージがある場合は先頭に追加
                if ($customMessage) {
                    $message = "【連絡事項】\n" . $customMessage . "\n\n" . $message;
                }
                
                // UTF-8として正しくエンコードされていることを確認
                $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
                $encodedMessage = base64_encode($message);
            }
            
            // メールヘッダーを設定
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: ' . $contentType,
                'Content-Transfer-Encoding: ' . $transferEncoding,
                'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
                'Reply-To: ' . $fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3'
            ];
            
            $headerString = implode("\r\n", $headers);
            $logs[] = "ヘッダー: " . str_replace("\r\n", ' | ', $headerString);
            
            // 日本語件名をエンコード
            $encodedSubject = encodeSubject($subject);
            $logs[] = "エンコード前件名: " . $subject;
            $logs[] = "エンコード後件名: " . $encodedSubject;
            
            $messageLength = mb_strlen($message, 'UTF-8');
            $logs[] = "メール本文長: {$messageLength}文字";
            
            // メール送信実行
            $logs[] = "mail()関数実行中...";
            $success = mail($to, $encodedSubject, $encodedMessage, $headerString);
            
            if ($success) {
                $result = '<div class="success">✅ メール送信成功！<br>送信先: ' . htmlspecialchars($to) . '<br>予約件数: ' . count($reservations) . '件</div>';
                $logs[] = "mail()戻り値: true";
            } else {
                $result = '<div class="error">❌ メール送信失敗 - mail()関数がfalseを返しました</div>';
                $logs[] = "mail()戻り値: false";
            }
            
        } catch (Exception $e) {
            $result = '<div class="error">❌ エラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $logs[] = "例外: " . $e->getMessage();
        }
        
        // エラーログも確認
        $errorLog = error_get_last();
        if ($errorLog && $errorLog['message']) {
            $logs[] = "最後のPHPエラー: " . $errorLog['message'];
        }
    }
}

// プレビュー用の予約データ取得
$previewReservations = [];
try {
    $previewReservations = getTodayReservations();
} catch (Exception $e) {
    $logs[] = "プレビュー用データ取得エラー: " . $e->getMessage();
}

// 現在の時刻を取得
$currentTime = date('Y-m-d H:i:s');
$today = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w')] . '）');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>本日の予約一覧メール送信テスト</title>
    <style>
        body {
            font-family: 'Yu Gothic', 'Hiragino Sans', sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="email"], input[type="text"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        textarea {
            height: 80px;
            resize: vertical;
        }
        button {
            background: #007cba;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #005a87;
        }
        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .logs {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .logs h3 {
            margin-top: 0;
            color: #495057;
        }
        .logs pre {
            margin: 0;
            font-size: 12px;
            line-height: 1.4;
            color: #6c757d;
        }
        .preview {
            background: #e2e3e5;
            border: 1px solid #d6d8db;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .preview h3 {
            margin-top: 0;
            color: #383d41;
        }
        .reservation-item {
            background: white;
            padding: 10px;
            margin: 5px 0;
            border-radius: 3px;
            border-left: 4px solid #007cba;
        }
        .reservation-time {
            font-weight: bold;
            color: #007cba;
        }
        .reservation-title {
            font-weight: bold;
            margin: 5px 0;
        }
        .reservation-user {
            color: #666;
            font-size: 14px;
        }
        .no-reservations {
            text-align: center;
            color: #666;
            padding: 20px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 本日の予約一覧メール送信テスト</h1>
        
        <div class="info">
            <strong>会議室予約システム - 予約一覧メール送信</strong><br>
            このツールは今日の予約一覧を取得してメールで送信します。<br>
            テスト日時: <?php echo $currentTime; ?> | 対象日: <?php echo $today; ?>
        </div>

        <div class="preview">
            <h3>📋 本日の予約一覧プレビュー</h3>
            <?php if (empty($previewReservations)): ?>
                <div class="no-reservations">本日の予約はありません</div>
            <?php else: ?>
                <p><strong>予約件数: <?php echo count($previewReservations); ?>件</strong></p>
                <?php foreach ($previewReservations as $index => $reservation): ?>
                    <?php 
                        $startTime = date('H:i', strtotime($reservation['start_datetime']));
                        $endTime = date('H:i', strtotime($reservation['end_datetime']));
                    ?>
                    <div class="reservation-item">
                        <div class="reservation-time"><?php echo $startTime; ?>～<?php echo $endTime; ?></div>
                        <div class="reservation-title"><?php echo htmlspecialchars($reservation['title']); ?></div>
                        <div class="reservation-user">
                            👤 <?php echo htmlspecialchars($reservation['user_name']); ?>
                            <?php if ($reservation['department']): ?>
                                (<?php echo htmlspecialchars($reservation['department']); ?>)
                            <?php endif; ?>
                        </div>
                        <?php if ($reservation['description']): ?>
                            <div style="margin-top: 5px; color: #666; font-size: 14px;">
                                📝 <?php echo htmlspecialchars($reservation['description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($result): ?>
            <?php echo $result; ?>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="to">送信先メールアドレス *</label>
                <input type="email" id="to" name="to" required 
                       value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>"
                       placeholder="user@example.com">
            </div>
            
            <div class="form-group">
                <label for="subject">件名</label>
                <input type="text" id="subject" name="subject" 
                       value="<?php echo htmlspecialchars($_POST['subject'] ?? '本日の会議室予約一覧 - ' . $today); ?>"
                       placeholder="本日の会議室予約一覧">
            </div>
            
            <div class="form-group">
                <label for="custom_message">追加メッセージ（オプション）</label>
                <textarea id="custom_message" name="custom_message" placeholder="予約一覧に追加で伝えたいメッセージがあれば入力してください..."><?php echo htmlspecialchars($_POST['custom_message'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="format">メール形式</label>
                <select id="format" name="format">
                    <option value="html" <?php echo ($_POST['format'] ?? 'html') === 'html' ? 'selected' : ''; ?>>HTML（リッチ表示）</option>
                    <option value="text" <?php echo ($_POST['format'] ?? '') === 'text' ? 'selected' : ''; ?>>テキスト（シンプル表示）</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="from_email">送信者メールアドレス</label>
                <input type="email" id="from_email" name="from_email" 
                       value="<?php echo htmlspecialchars($_POST['from_email'] ?? 'meeting-room-reservation@jama.co.jp'); ?>"
                       placeholder="meeting-room-reservation@jama.co.jp">
            </div>
            
            <div class="form-group">
                <label for="from_name">送信者名</label>
                <input type="text" id="from_name" name="from_name" 
                       value="<?php echo htmlspecialchars($_POST['from_name'] ?? '会議室予約システム'); ?>"
                       placeholder="会議室予約システム">
            </div>
            
            <button type="submit">📤 予約一覧メール送信</button>
        </form>

        <?php if (!empty($logs)): ?>
        <div class="logs">
            <h3>送信ログ</h3>
            <pre><?php echo htmlspecialchars(implode("\n", $logs)); ?></pre>
        </div>
        <?php endif; ?>
        
        <div class="info" style="margin-top: 30px;">
            <strong>使用方法:</strong><br>
            1. 送信先メールアドレスを入力<br>
            2. 件名や追加メッセージを必要に応じて調整<br>
            3. メール形式（HTML/テキスト）を選択<br>
            4. 「予約一覧メール送信」ボタンをクリック<br>
            5. 送信結果とログを確認<br><br>
            <strong>注意:</strong> このツールは本日（<?php echo $today; ?>）の予約のみを対象とします。
        </div>
    </div>
</body>
</html>