<?php
require_once '../api/config.php';

try {
    echo "Starting department migration...\n";
    
    // データベース接続を取得
    $pdo = getDatabase();
    
    // Create departments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        display_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created departments table.\n";
    
    // Insert initial department data
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO departments (id, name, display_order) VALUES (?, ?, ?)");
    $departments = [
        [1, '取締役', 1],
        [2, '総務管理部', 2],
        [3, '営業開発推進部', 3],
        [4, '制作部', 4]
    ];
    
    foreach ($departments as $dept) {
        $stmt->execute($dept);
    }
    echo "Inserted initial department data.\n";
    
    // Add new department column as INTEGER
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN department_new INTEGER DEFAULT 1");
        echo "Added new department_new column.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false) {
            echo "Column department_new already exists.\n";
        } else {
            throw $e;
        }
    }
    
    // Migrate existing department data (if any TEXT values exist)
    $stmt = $pdo->query("SELECT id, department FROM users WHERE department IS NOT NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $pdo->prepare("UPDATE users SET department_new = ? WHERE id = ?");
    
    foreach ($users as $user) {
        $deptId = 1; // Default to first department
        
        // Try to match text department to ID
        if ($user['department']) {
            switch (trim($user['department'])) {
                case '取締役':
                    $deptId = 1;
                    break;
                case '総務管理部':
                    $deptId = 2;
                    break;
                case '営業開発推進部':
                    $deptId = 3;
                    break;
                case '制作部':
                    $deptId = 4;
                    break;
                default:
                    // If it's already a number, use it
                    if (is_numeric($user['department'])) {
                        $deptId = (int)$user['department'];
                    }
            }
        }
        
        $updateStmt->execute([$deptId, $user['id']]);
    }
    echo "Migrated existing department data.\n";
    
    // Drop old department column and rename new one
    $pdo->exec("ALTER TABLE users DROP COLUMN department");
    echo "Dropped old department column.\n";
    
    $pdo->exec("ALTER TABLE users RENAME COLUMN department_new TO department");
    echo "Renamed department_new to department.\n";
    
    // Update email_notification_type default and existing values
    $pdo->exec("UPDATE users SET email_notification_type = 2 WHERE email_notification_type = 3");
    echo "Updated email_notification_type values from 3 to 2.\n";
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>