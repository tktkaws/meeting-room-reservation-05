<?php
// 予約CRUD Google Chat通知機能
// この機能は削除する可能性があるので既存の処理とは別ファイルで実装

require_once 'config.php';

// Google Chat Webhook URL  
define('GOOGLE_CHAT_WEBHOOK_URL', 'https://chat.googleapis.com/v1/spaces/AAQAeweySDs/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=U9BskCyb-xy1N6x6ed9WnK69epAW70geElfVkB2RExc');

/**
 * Google Chatに予約通知を送信
 * @param array $reservation 予約データ
 * @param string $action アクション種別 (created, updated, deleted)
 * @return bool 送信成功/失敗
 */
function sendReservationChatNotification($reservation, $action) {
    try {
        // より詳細なログを出力
        $logFile = __DIR__ . '/../scripts/chat_notification.log';
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Chat通知開始: ID={$reservation['id']}, Action={$action}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        error_log("Chat通知開始: ID={$reservation['id']}, Action={$action}");
        
        // 予約詳細情報を取得
        $reservationDetail = getReservationDetailForChat($reservation['id']);
        if (!$reservationDetail) {
            $errorMsg = "Chat通知: 予約詳細の取得に失敗 - ID: {$reservation['id']}";
            error_log($errorMsg);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", FILE_APPEND | LOCK_EX);
            return false;
        }
        
        $successMsg = "Chat通知: 予約詳細取得成功 - タイトル: {$reservationDetail['title']}";
        error_log($successMsg);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $successMsg . "\n", FILE_APPEND | LOCK_EX);
        
        // 通知メッセージを作成
        $message = createChatMessage($reservationDetail, $action);
        $msgSize = "Chat通知: メッセージ作成完了 - サイズ: " . strlen(json_encode($message)) . " bytes";
        error_log($msgSize);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msgSize . "\n", FILE_APPEND | LOCK_EX);
        
        // Google Chatに送信
        $result = sendToGoogleChat($message);
        $resultMsg = "Chat通知: 送信結果 - " . ($result ? "成功" : "失敗");
        error_log($resultMsg);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $resultMsg . "\n", FILE_APPEND | LOCK_EX);
        
        return $result;
        
    } catch (Exception $e) {
        $errorMsg = "Chat通知エラー: " . $e->getMessage();
        error_log($errorMsg);
        error_log("Chat通知エラー詳細: " . $e->getTraceAsString());
        
        $logFile = __DIR__ . '/../scripts/chat_notification.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", FILE_APPEND | LOCK_EX);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $e->getTraceAsString() . "\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}

/**
 * 予約詳細情報を取得（Chat通知用）
 * @param int $reservationId 予約ID
 * @return array|null 予約詳細データ
 */
function getReservationDetailForChat($reservationId) {
    try {
        $logFile = __DIR__ . '/../scripts/chat_notification.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 予約詳細取得開始: ID={$reservationId}\n", FILE_APPEND | LOCK_EX);
        
        $db = getDatabase();
        
        $stmt = $db->prepare("
            SELECT r.*, u.name as user_name, u.department, rg.repeat_type, rg.repeat_interval 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            LEFT JOIN reservation_groups rg ON r.group_id = rg.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 予約が見つかりません: ID={$reservationId}\n", FILE_APPEND | LOCK_EX);
            return null;
        }
        
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 予約詳細取得成功: ID={$reservationId}, Title={$reservation['title']}, Date={$reservation['date']}\n", FILE_APPEND | LOCK_EX);
        
        return $reservation;
        
    } catch (Exception $e) {
        $logFile = __DIR__ . '/../scripts/chat_notification.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 予約詳細取得エラー (Chat用): " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        error_log("予約詳細取得エラー (Chat用): " . $e->getMessage());
        return null;
    }
}

/**
 * Chat用メッセージを作成
 * @param array $reservation 予約データ
 * @param string $action アクション種別
 * @return array Chatメッセージ配列
 */
function createChatMessage($reservation, $action) {
    // アクション種別に応じたアイコンとタイトル
    $actionInfo = getChatActionInfo($action);
    
    // 日付フォーマット
    $date = new DateTime($reservation['date']);
    $formattedDate = $date->format('n月j日') . '(' . ['日', '月', '火', '水', '木', '金', '土'][$date->format('w')] . ')';
    
    // 時間フォーマット
    $startTime = substr($reservation['start_datetime'], 11, 5);
    $endTime = substr($reservation['end_datetime'], 11, 5);
    $timeRange = $startTime . ' - ' . $endTime;
    
    // 繰り返し予約情報
    $repeatInfo = '';
    if ($reservation['group_id']) {
        $repeatTypes = [
            'daily' => '毎日',
            'weekly' => '毎週',
            'monthly' => '毎月'
        ];
        $repeatInfo = "🔁 " . ($repeatTypes[$reservation['repeat_type']] ?? $reservation['repeat_type']);
    }
    
    // カードメッセージを作成（ヘッダーなし、アクション文言を本文先頭に）
    $message = [
        'cards' => [
            [
                'sections' => [
                    [
                        'widgets' => [
                            [
                                'keyValue' => [
                                    'topLabel' => 'アクション',
                                    'content' => $actionInfo['title']
                                ]
                            ],
                            [
                                'keyValue' => [
                                    'topLabel' => '日付',
                                    'content' => $formattedDate
                                ]
                            ],
                            [
                                'keyValue' => [
                                    'topLabel' => '時間',
                                    'content' => $timeRange
                                ]
                            ],
                            [
                                'keyValue' => [
                                    'topLabel' => 'タイトル',
                                    'content' => $reservation['title']
                                ]
                            ],
                            [
                                'keyValue' => [
                                    'topLabel' => '予約者',
                                    'content' => $reservation['user_name'] . ' (' . ($reservation['department'] ?: '部署未設定') . ')'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // 説明がある場合は追加
    if (!empty($reservation['description'])) {
        $message['cards'][0]['sections'][0]['widgets'][] = [
            'keyValue' => [
                'topLabel' => '説明',
                'content' => $reservation['description']
            ]
        ];
    }
    
    // 繰り返し予約情報がある場合は追加
    if ($repeatInfo) {
        $message['cards'][0]['sections'][0]['widgets'][] = [
            'keyValue' => [
                'topLabel' => '繰り返し',
                'content' => $repeatInfo
            ]
        ];
    }
    
    return $message;
}

/**
 * アクション種別に応じた情報を取得
 * @param string $action アクション種別
 * @return array タイトルとアイコン情報
 */
function getChatActionInfo($action) {
    switch ($action) {
        case 'created':
            return [
                'title' => '📅 新しい予約が作成されました',
                'icon' => 'https://developers.google.com/chat/images/quickstart-app-avatar.png'
            ];
        case 'updated':
            return [
                'title' => '✏️ 予約が更新されました',
                'icon' => 'https://developers.google.com/chat/images/quickstart-app-avatar.png'
            ];
        case 'deleted':
            return [
                'title' => '🗑️ 予約が削除されました',
                'icon' => 'https://developers.google.com/chat/images/quickstart-app-avatar.png'
            ];
        default:
            return [
                'title' => '📋 予約が変更されました',
                'icon' => 'https://developers.google.com/chat/images/quickstart-app-avatar.png'
            ];
    }
}

/**
 * Google Chatにメッセージを送信
 * @param array $message メッセージデータ
 * @return bool 送信成功/失敗
 */
function sendToGoogleChat($message) {
    try {
        $logFile = __DIR__ . '/../scripts/chat_notification.log';
        
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Google Chat送信開始\n", FILE_APPEND | LOCK_EX);
        error_log("Google Chat送信開始");
        
        $urlLog = "送信URL: " . substr(GOOGLE_CHAT_WEBHOOK_URL, 0, 80) . "...";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $urlLog . "\n", FILE_APPEND | LOCK_EX);
        error_log($urlLog);
        
        $jsonData = json_encode($message);
        $sizeLog = "送信データサイズ: " . strlen($jsonData) . " bytes";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $sizeLog . "\n", FILE_APPEND | LOCK_EX);
        error_log($sizeLog);
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, GOOGLE_CHAT_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL検証を無効化（テスト用）
        
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] cURL実行開始\n", FILE_APPEND | LOCK_EX);
        error_log("cURL実行開始");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        $codeLog = "cURL実行完了 - HTTPコード: {$httpCode}";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $codeLog . "\n", FILE_APPEND | LOCK_EX);
        error_log($codeLog);
        
        if ($error) {
            $errorLog = "Google Chat送信エラー (cURL): " . $error;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorLog . "\n", FILE_APPEND | LOCK_EX);
            error_log($errorLog);
            return false;
        }
        
        if ($httpCode !== 200) {
            $httpErrorLog = "Google Chat送信エラー (HTTP {$httpCode}): " . $response;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $httpErrorLog . "\n", FILE_APPEND | LOCK_EX);
            error_log($httpErrorLog);
            return false;
        }
        
        $successLog = "Google Chat通知送信成功: " . $response;
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $successLog . "\n", FILE_APPEND | LOCK_EX);
        error_log($successLog);
        return true;
        
    } catch (Exception $e) {
        $exceptionLog = "Google Chat送信例外: " . $e->getMessage();
        $logFile = __DIR__ . '/../scripts/chat_notification.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $exceptionLog . "\n", FILE_APPEND | LOCK_EX);
        error_log($exceptionLog);
        return false;
    }
}

/**
 * 予約作成時の通知
 * @param array $reservation 予約データ
 */
function notifyReservationCreated($reservation) {
    $logFile = __DIR__ . '/../scripts/chat_notification.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] notifyReservationCreated called with ID: " . ($reservation['id'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
    return sendReservationChatNotification($reservation, 'created');
}

/**
 * 予約更新時の通知
 * @param array $reservation 予約データ
 */
function notifyReservationUpdated($reservation) {
    $logFile = __DIR__ . '/../scripts/chat_notification.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] notifyReservationUpdated called with ID: " . ($reservation['id'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
    return sendReservationChatNotification($reservation, 'updated');
}

/**
 * 予約削除時の通知
 * @param array $reservation 予約データ
 */
function notifyReservationDeleted($reservation) {
    $logFile = __DIR__ . '/../scripts/chat_notification.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] notifyReservationDeleted called with ID: " . ($reservation['id'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
    return sendReservationChatNotification($reservation, 'deleted');
}

/**
 * 非同期Chat通知をスケジュール
 * @param int $reservationId 予約ID
 * @param string $action アクション種別
 */
function scheduleAsyncChatNotification($reservationId, $action) {
    // JavaScriptから非同期で呼び出すための情報をファイルに保存
    $notificationData = [
        'reservation_id' => $reservationId,
        'action' => $action,
        'timestamp' => time()
    ];
    
    $logFile = __DIR__ . '/../scripts/chat_notification.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 非同期Chat通知スケジュール: ID={$reservationId}, Action={$action}\n", FILE_APPEND | LOCK_EX);
    
    // JavaScriptで処理するため、ここでは何もしない
    return true;
}

/**
 * 削除時の同期Chat通知（削除前に取得したデータを使用）
 * @param array $reservationData 削除前に取得した予約データ
 */
function sendReservationChatNotificationForDeleted($reservationData) {
    $logFile = __DIR__ . '/../scripts/chat_notification.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 削除用Chat通知開始: ID=" . ($reservationData['id'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
    
    try {
        // 削除の場合は取得済みデータを直接使用してメッセージを作成
        $message = createChatMessage($reservationData, 'deleted');
        $messageSize = "Chat通知: メッセージ作成完了 - サイズ: " . strlen(json_encode($message)) . " bytes";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $messageSize . "\n", FILE_APPEND | LOCK_EX);
        
        // Google Chatに送信
        $result = sendToGoogleChat($message);
        $resultMsg = "Chat通知: 送信結果 - " . ($result ? "成功" : "失敗");
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $resultMsg . "\n", FILE_APPEND | LOCK_EX);
        
        return $result;
        
    } catch (Exception $e) {
        $errorMsg = "削除Chat通知エラー: " . $e->getMessage();
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", FILE_APPEND | LOCK_EX);
        error_log($errorMsg);
        return false;
    }
}
?>