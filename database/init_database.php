<?php
// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// Initialize SQLite database
try {
    $db = new PDO('sqlite:meeting_room.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute schema
    $schema = file_get_contents('schema.sql');
    $db->exec($schema);
    
    echo "Database initialized successfully!\n";
    
    // Insert sample admin user
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, department) VALUES (?, ?, ?, ?, ?)");
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt->execute(['Admin User', 'admin@example.com', $hashedPassword, 'admin', 'IT']);
    
    // Insert sample regular user
    $stmt->execute(['Test User', 'user@example.com', password_hash('user123', PASSWORD_DEFAULT), 'user', 'General']);
    
    echo "Sample users created!\n";
    echo "Admin: admin@example.com / admin123\n";
    echo "User: user@example.com / user123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>