<?php
/**
 * メールテンプレート処理機能
 */

/**
 * テンプレートファイルを読み込んでメール本文を生成
 * @param string $templatePath テンプレートファイルのパス
 * @param array $variables テンプレート内で使用する変数の連想配列
 * @return string 生成されたメール本文
 */
function loadMailTemplate($templatePath, $variables = []) {
    if (!file_exists($templatePath)) {
        throw new Exception("テンプレートファイルが見つかりません: {$templatePath}");
    }
    
    // 変数を展開（衝突する場合は接頭辞を付ける）
    extract($variables, EXTR_PREFIX_SAME, 'tmpl');
    
    // テンプレートをバッファリングして読み込み
    ob_start();
    ob_implicit_flush(false);
    
    try {
        include $templatePath;
        $content = ob_get_contents();
        
        // バッファの内容が空でないことを確認
        if ($content === false || strlen($content) === 0) {
            throw new Exception("テンプレートの内容が空です");
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        throw new Exception("テンプレート処理エラー: " . $e->getMessage());
    }
    
    ob_end_clean();
    
    // 改行コードを統一（LFに変換）
    $content = strtr($content, ["\r\n" => "\n", "\r" => "\n"]);
    
    // 不正な文字を除去
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    
    return $content;
}

/**
 * 予約データからテンプレート変数を生成
 * @param array $reservation 予約データ
 * @param string $action アクション種別
 * @return array テンプレート変数配列
 */
function generateTemplateVariables($reservation, $action) {
    $actionText = [
        'created' => '新規予約',
        'updated' => '予約変更',
        'deleted' => '予約削除'
    ];
    
    $actionEmoji = [
        'created' => '✅',
        'updated' => '🔄',
        'deleted' => '🗑️'
    ];
    
    $actionColors = [
        'created' => '#28a745',
        'updated' => '#007bff',
        'deleted' => '#dc3545'
    ];
    
    $actionLabel = $actionText[$action] ?? '予約通知';
    $emoji = $actionEmoji[$action] ?? '📅';
    $actionColor = $actionColors[$action] ?? '#333';
    
    // 日付フォーマット
    $dateFormatted = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($reservation['date']))] . '）', strtotime($reservation['date']));
    $startTime = date('H:i', strtotime($reservation['start_datetime']));
    $endTime = date('H:i', strtotime($reservation['end_datetime']));
    $sendDatetime = date('Y年n月j日 H:i');
    
    return [
        'action_label' => $actionLabel,
        'action_emoji' => $emoji,
        'action_color' => $actionColor,
        'title' => trim($reservation['title'] ?? ''),
        'description' => trim($reservation['description'] ?? ''),
        'date_formatted' => $dateFormatted,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'user_name' => trim($reservation['user_name'] ?? ''),
        'department' => trim($reservation['department_name'] ?? '未設定'),
        'send_datetime' => $sendDatetime
    ];
}

/**
 * テキストメール本文を生成
 * @param array $reservation 予約データ
 * @param string $action アクション種別
 * @return string メール本文
 */
function generateTextMailFromTemplate($reservation, $action) {
    $templatePath = __DIR__ . '/../templates/mail/reservation_text.php';
    $variables = generateTemplateVariables($reservation, $action);
    return loadMailTemplate($templatePath, $variables);
}

/**
 * HTMLメール本文を生成
 * @param array $reservation 予約データ
 * @param string $action アクション種別
 * @return string メール本文
 */
function generateHtmlMailFromTemplate($reservation, $action) {
    $templatePath = __DIR__ . '/../templates/mail/reservation_html.php';
    $variables = generateTemplateVariables($reservation, $action);
    return loadMailTemplate($templatePath, $variables);
}

/**
 * メール件名を生成
 * @param array $reservation 予約データ
 * @param string $action アクション種別
 * @return string メール件名
 */
function generateMailSubject($reservation, $action) {
    $actionText = [
        'created' => '新規予約',
        'updated' => '予約変更',
        'deleted' => '予約削除'
    ];
    
    $actionLabel = $actionText[$action] ?? '予約通知';
    
    // 日付フォーマット: 2025年7月11日（金）
    $date = new DateTime($reservation['date']);
    $dateFormatted = $date->format('Y年n月j日') . '（' . ['日', '月', '火', '水', '木', '金', '土'][$date->format('w')] . '）';
    
    // 時間フォーマット: 12:45～13:45
    $startTime = date('G:i', strtotime($reservation['start_datetime']));
    $endTime = date('G:i', strtotime($reservation['end_datetime']));
    
    // 件名: [新規予約] 2025年7月11日（金）12:45～13:45
    return "[{$actionLabel}] {$dateFormatted}{$startTime}～{$endTime}";
}
?>