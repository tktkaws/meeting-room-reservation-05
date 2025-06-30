<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会議室予約通知</title>
    <style>
        body {
            font-family: "メイリオ", "Hiragino Sans", "Yu Gothic", "游ゴシック", sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            padding: 20px;
            background-color: #fff;
            font-size: 16px;
        }
        
        .mail-header {
            text-align: left;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .action-label {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .detail-section {
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 0.75rem;
            align-items: flex-start;
        }
        
        .detail-item label {
            font-weight: 600;
            min-width: 100px;
            color: #555;
            margin-right: 1rem;
        }
        
        .detail-item span {
            flex: 1;
            color: #333;
        }
        
        .mail-footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .mail-footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .mail-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="mail-header">
        <div class="action-label"><?= htmlspecialchars((isset($action_emoji) ? $action_emoji : '') . ' ' . (isset($action_label) ? $action_label : '予約通知'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    
    <div class="detail-section">
        <div class="detail-item">
            <label>日付</label>
            <span><?= htmlspecialchars(isset($date_formatted) ? $date_formatted : '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <label>時間</label>
            <span><?= htmlspecialchars((isset($start_time) ? $start_time : '') . '～' . (isset($end_time) ? $end_time : ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <label>タイトル</label>
            <span><?= htmlspecialchars(isset($title) ? $title : '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <label>予約者</label>
            <span><?= htmlspecialchars((isset($user_name) ? $user_name : '') . ((isset($department) && $department) ? ' (' . $department . ')' : ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <!-- <?php if (isset($description) && !empty(trim($description))): ?>
        <div class="detail-item">
            <label>説明</label>
            <span><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?> -->
    </div>
    
    <div class="mail-footer">
        <p><a href="http://intra2.jama.co.jp/meeting-room-reservation-05/">http://intra2.jama.co.jp/meeting-room-reservation-05/</a></p>
        <p>送信日時: <?= htmlspecialchars(isset($send_datetime) ? $send_datetime : date('Y年n月j日 H:i'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</body>
</html>