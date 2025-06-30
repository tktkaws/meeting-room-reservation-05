<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['date'])) {
    http_response_code(400);
    echo json_encode(['error' => '日付が指定されていません']);
    exit;
}

$date = $input['date'];

// 認証チェック（必要に応じてコメントアウト）
// requireAuth();

// 指定日付の予約データを取得
try {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        SELECT r.id, r.title, r.description, r.start_datetime, r.end_datetime, u.name as user_name
        FROM reservations r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.date = ?
        ORDER BY r.start_datetime
    ");
    
    $stmt->execute([$date]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Google Chat用のメッセージを作成
    $weekday = ['日', '月', '火', '水', '木', '金', '土'];
    $dateFormatted = date('m月d日', strtotime($date)) . '(' . $weekday[date('w', strtotime($date))] . ')';
    $message = "*{$dateFormatted}の予定*\n\n";
    
    if (empty($reservations)) {
        $message .= "本日は予約がありません。";
    } else {
        foreach ($reservations as $reservation) {
            $startTime = date('H:i', strtotime($reservation['start_datetime']));
            $endTime = date('H:i', strtotime($reservation['end_datetime']));
            $message .= "🕐 *{$startTime} - {$endTime}*\n";
            $message .= "📋 {$reservation['title']}\n";
            if (!empty($reservation['description'])) {
                $message .= "📝 {$reservation['description']}\n";
            }
            $message .= "👤 {$reservation['user_name']}\n\n";
        }
    }
    $message .= "http://intra2.jama.co.jp/meeting-room-reservation-05/";
    
    // Google Chat Webhook URL
    $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAQAW4CXATk/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=a_50V7YZ5Ix3hbh-sF-ez8apzMnrB_mbbxAaQDwB_ZQ';
    
    // Google Chatに投稿
    $postData = json_encode(['text' => $message]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'Google Chatに投稿しました',
            'posted_message' => $message
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'error' => 'Google Chatへの投稿に失敗しました',
            'http_code' => $httpCode,
            'response' => $response
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>