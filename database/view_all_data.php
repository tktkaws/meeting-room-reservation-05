<?php
require_once '../api/config.php';

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース一覧 - 会議室予約システム</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #007bff;
            margin-top: 30px;
            border-left: 4px solid #007bff;
            padding-left: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background-color: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e3f2fd;
        }
        .count {
            background-color: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../index.html" class="back-link">← メインページに戻る</a>
        
        <h1>データベース一覧情報</h1>
        <p style="text-align: center; color: #6c757d;">現在のデータベースに登録されている全データの一覧です</p>

<?php
try {
    $pdo = getDatabase();
    
    // 1. ユーザーテーブル
    echo "<h2>ユーザー一覧 <span class='count'>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo $count . "件</span></h2>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, name, email, role, department, email_notification_type, created_at FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>名前</th><th>メールアドレス</th><th>権限</th><th>部署ID</th><th>メール通知設定</th><th>作成日時</th></tr>";
        foreach ($users as $user) {
            $notification_type = $user['email_notification_type'] == 1 ? '通知する' : '通知しない';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['department']) . "</td>";
            echo "<td>" . htmlspecialchars($notification_type) . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='no-data'>ユーザーデータはありません</div>";
    }
    
    // 2. 部署テーブル
    echo "<h2>部署一覧 <span class='count'>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $count = $stmt->fetch()['count'];
    echo $count . "件</span></h2>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, name, display_order, created_at FROM departments ORDER BY display_order, id");
        $departments = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>部署名</th><th>表示順</th><th>作成日時</th></tr>";
        foreach ($departments as $dept) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($dept['id']) . "</td>";
            echo "<td>" . htmlspecialchars($dept['name']) . "</td>";
            echo "<td>" . htmlspecialchars($dept['display_order']) . "</td>";
            echo "<td>" . htmlspecialchars($dept['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='no-data'>部署データはありません</div>";
    }
    
    // 3. 予約グループテーブル
    echo "<h2>予約グループ一覧 <span class='count'>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservation_groups");
    $count = $stmt->fetch()['count'];
    echo $count . "件</span></h2>";
    
    if ($count > 0) {
        $stmt = $pdo->query("
            SELECT rg.id, rg.title, rg.description, rg.user_id, u.name as user_name, 
                   rg.repeat_type, rg.repeat_interval, rg.start_date, rg.end_date, 
                   rg.days_of_week, rg.created_at
            FROM reservation_groups rg 
            LEFT JOIN users u ON rg.user_id = u.id 
            ORDER BY rg.id DESC
        ");
        $groups = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>タイトル</th><th>説明</th><th>作成者</th><th>繰り返し</th><th>間隔</th><th>開始日</th><th>終了日</th><th>曜日</th><th>作成日時</th></tr>";
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($group['id']) . "</td>";
            echo "<td>" . htmlspecialchars($group['title']) . "</td>";
            echo "<td>" . htmlspecialchars($group['description'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($group['user_name'] ?? 'ID:' . $group['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($group['repeat_type']) . "</td>";
            echo "<td>" . htmlspecialchars($group['repeat_interval']) . "</td>";
            echo "<td>" . htmlspecialchars($group['start_date']) . "</td>";
            echo "<td>" . htmlspecialchars($group['end_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($group['days_of_week'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($group['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='no-data'>予約グループデータはありません</div>";
    }
    
    // 4. 予約テーブル
    echo "<h2>予約一覧 <span class='count'>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations");
    $count = $stmt->fetch()['count'];
    echo $count . "件</span></h2>";
    
    if ($count > 0) {
        $stmt = $pdo->query("
            SELECT r.id, r.title, r.description, r.user_id, u.name as user_name,
                   r.date, r.start_datetime, r.end_datetime, r.group_id, 
                   rg.title as group_title, r.created_at, r.updated_at
            FROM reservations r 
            LEFT JOIN users u ON r.user_id = u.id 
            LEFT JOIN reservation_groups rg ON r.group_id = rg.id
            ORDER BY r.date DESC, r.start_datetime DESC
            LIMIT 50
        ");
        $reservations = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>タイトル</th><th>説明</th><th>予約者</th><th>日付</th><th>開始時刻</th><th>終了時刻</th><th>グループ</th><th>作成日時</th><th>更新日時</th></tr>";
        foreach ($reservations as $reservation) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($reservation['id']) . "</td>";
            echo "<td>" . htmlspecialchars($reservation['title']) . "</td>";
            echo "<td>" . htmlspecialchars($reservation['description'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($reservation['user_name'] ?? 'ID:' . $reservation['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($reservation['date']) . "</td>";
            echo "<td>" . htmlspecialchars(date('H:i', strtotime($reservation['start_datetime']))) . "</td>";
            echo "<td>" . htmlspecialchars(date('H:i', strtotime($reservation['end_datetime']))) . "</td>";
            echo "<td>" . htmlspecialchars($reservation['group_title'] ?? ($reservation['group_id'] ? 'ID:' . $reservation['group_id'] : '単発')) . "</td>";
            echo "<td>" . htmlspecialchars($reservation['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($reservation['updated_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($count > 50) {
            echo "<p style='text-align: center; color: #6c757d;'>※最新50件のみ表示しています（全" . $count . "件）</p>";
        }
    } else {
        echo "<div class='no-data'>予約データはありません</div>";
    }

} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; background-color: #fff5f5; border: 1px solid #fed7d7; border-radius: 4px;'>";
    echo "<h3>エラーが発生しました</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

        <div style="margin-top: 30px; text-align: center; color: #6c757d; font-size: 12px;">
            <p>最終更新: <?php echo date('Y年m月d日 H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>