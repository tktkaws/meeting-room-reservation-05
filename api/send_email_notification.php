<?php
/**
 * CRUD時のメール通知送信API
 * email_notification_type = 1 のユーザーに予約変更通知を送信
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// エラーレポート設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// ログファイル
$logFile = __DIR__ . '/../logs/email_notification.log';

// ログディレクトリが存在しない場合は作成
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * ログ出力
 */
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * メール件名をエンコードする関数
 */
function encodeSubject($subject) {
    // ASCII文字のみの場合はエンコードしない
    if (preg_match('/^[\x20-\x7E]*$/', $subject)) {
        return $subject;
    }
    
    // 文字列が長すぎる場合は短縮
    $maxLength = 40;
    if (mb_strlen($subject, 'UTF-8') > $maxLength) {
        $subject = mb_substr($subject, 0, $maxLength - 3, 'UTF-8') . '...';
    }
    
    // Base64エンコード
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

/**
 * メール通知対象ユーザーを取得
 */
function getEmailNotificationUsers() {
    try {
        $db = getDatabase();
        
        $sql = "
            SELECT id, name, email, department
            FROM users 
            WHERE email_notification_type = 1
            ORDER BY name ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $users;
        
    } catch (Exception $e) {
        writeLog("対象ユーザー取得エラー: " . $e->getMessage());
        return [];
    }
}

/**
 * 予約詳細を取得
 */
function getReservationDetails($reservationId) {
    try {
        $db = getDatabase();
        
        $sql = "
            SELECT r.*, u.name as user_name, u.department as user_department, d.name as department_name
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            LEFT JOIN departments d ON u.department = d.id 
            WHERE r.id = ?
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $reservation;
        
    } catch (Exception $e) {
        writeLog("予約詳細取得エラー: " . $e->getMessage());
        return null;
    }
}

/**
 * テキストメール本文を生成（絵文字なし）
 */
function generateEmailContentText($reservation, $action) {
    $actionText = [
        'created' => '新規予約',
        'updated' => '予約変更',
        'deleted' => '予約削除'
    ];
    
    $actionLabel = $actionText[$action] ?? '予約通知';
    $date = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($reservation['date']))] . '）', strtotime($reservation['date']));
    $startTime = date('H:i', strtotime($reservation['start_datetime']));
    $endTime = date('H:i', strtotime($reservation['end_datetime']));
    
    $content = "会議室予約通知 - 【{$actionLabel}】\n\n";
    $content .= "========================================\n";
    $content .= "タイトル: " . $reservation['title'] . "\n";
    $content .= "日時: {$date} {$startTime}～{$endTime}\n";
    $content .= "予約者: " . $reservation['user_name'];
    if ($reservation['department_name']) {
        $content .= " (" . $reservation['department_name'] . ")";
    }
    $content .= "\n";
    
    if ($reservation['description']) {
        $content .= "内容: " . $reservation['description'] . "\n";
    }
    
    $content .= "========================================\n\n";
    $content .= "会議室予約システム\n";
    $content .= "URL: http://intra2.jama.co.jp/meeting-room-reservation-05/\n";
    $content .= "送信日時: " . date('Y年n月j日 H:i') . "\n";
    
    return $content;
}

/**
 * メール送信
 */
function sendEmail($to, $subject, $message, $isHtml = false) {
    try {
        $messageLength = mb_strlen($message, 'UTF-8');
        writeLog("メール送信開始: to={$to}, subject={$subject}, 形式=" . ($isHtml ? 'HTML' : 'テキスト') . ", 本文長={$messageLength}文字");
        
        $fromEmail = 'meeting-room-reservation@jama.co.jp';
        $fromName = '会議室予約システム';
        
        // メール本文の長さチェック
        $maxLength = 1000000; // 1000KB程度を上限とする
        if ($messageLength > $maxLength) {
            writeLog("❌ メール本文が長すぎます: {$messageLength}文字 (上限: {$maxLength}文字)");
            return false;
        }
        
        if ($isHtml) {
            // HTMLメールの場合 - base64エンコーディングに変更
            $contentType = 'text/html; charset=UTF-8';
            $transferEncoding = 'base64';
            // UTF-8として正しくエンコードされていることを確認
            $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
            $encodedMessage = base64_encode($message);
            $encodedLength = strlen($encodedMessage);
            writeLog("Base64エンコード後のサイズ: {$encodedLength}バイト");
        } else {
            // テキストメールの場合 - base64エンコーディングに変更
            $contentType = 'text/plain; charset=UTF-8';
            $transferEncoding = 'base64';
            // UTF-8として正しくエンコードされていることを確認
            $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
            $encodedMessage = base64_encode($message);
            $encodedLength = strlen($encodedMessage);
            writeLog("Base64エンコード後のサイズ: {$encodedLength}バイト");
        }
        
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
        // 件名のエンコーディング
        $encodedSubject = encodeSubject($subject);
        
        $success = mail($to, $encodedSubject, $encodedMessage, $headerString);
        
        if ($success) {
            writeLog("✅ メール送信成功: {$to}");
            return true;
        } else {
            writeLog("❌ メール送信失敗: {$to}");
            return false;
        }
        
    } catch (Exception $e) {
        writeLog("❌ メール送信エラー: " . $e->getMessage());
        return false;
    }
}

// メイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode([
            'success' => false,
            'message' => '無効なJSONデータです'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $reservationId = $input['reservation_id'] ?? null;
    $action = $input['action'] ?? null;
    $reservationData = $input['reservation_data'] ?? null; // 削除時の事前取得データ
    
    if (!$reservationId || !$action) {
        echo json_encode([
            'success' => false,
            'message' => '予約IDとアクションが必要です'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    writeLog("メール通知処理開始: 予約ID={$reservationId}, アクション={$action}");
    
    // 予約詳細を取得
    if ($action === 'deleted' && $reservationData) {
        // 削除の場合は事前取得したデータを使用
        $reservation = $reservationData;
        writeLog("削除通知: 事前取得データを使用");
    } else {
        // 作成・更新の場合は通常通りDBから取得
        $reservation = getReservationDetails($reservationId);
        if (!$reservation) {
            echo json_encode([
                'success' => false,
                'message' => '予約が見つかりません'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 通知対象ユーザーを取得
    $targetUsers = getEmailNotificationUsers();
    if (empty($targetUsers)) {
        writeLog("通知対象ユーザーがいません");
        echo json_encode([
            'success' => true,
            'message' => '通知対象ユーザーがいません',
            'sent_count' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // メール内容を生成（テンプレートを使用）
    require_once __DIR__ . '/mail_template.php';
    $htmlContent = generateHtmlMailFromTemplate($reservation, $action);
    $textContent = generateTextMailFromTemplate($reservation, $action);
    
    // 新しい件名生成関数を使用
    $subject = generateMailSubject($reservation, $action);
    
    // メール送信
    $successCount = 0;
    $failCount = 0;
    
    foreach ($targetUsers as $user) {
        // テキストメールで送信
        if (sendEmail($user['email'], $subject, $textContent, false)) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    writeLog("メール通知完了: 成功={$successCount}, 失敗={$failCount}");
    
    echo json_encode([
        'success' => true,
        'message' => "メール通知送信完了: {$successCount}名に送信（失敗: {$failCount}名）",
        'sent_count' => $successCount,
        'failed_count' => $failCount,
        'target_users' => count($targetUsers)
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'POSTメソッドのみサポートしています'
    ], JSON_UNESCAPED_UNICODE);
}
?>