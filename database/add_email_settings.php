<?php
// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// Add email notification settings to existing database
try {
    $db = new PDO('sqlite:meeting_room.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column already exists
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $columnExists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'email_notification_type') {
            $columnExists = true;
            break;
        }
    }
    
    if (!$columnExists) {
        // Add email notification type column
        $db->exec("ALTER TABLE users ADD COLUMN email_notification_type INTEGER DEFAULT 3");
        echo "Email notification settings column added successfully!\n";
    } else {
        echo "Email notification settings column already exists.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>