<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>テーマカラー機能スキーマ更新</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .sql-content {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 15px 0;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 テーマカラー機能スキーマ更新</h1>
        
        <?php
        // データベース設定を読み込み
        require_once '../api/config.php';
        
        $executed = false;
        $results = [];
        
        if (isset($_POST['execute'])) {
            $executed = true;
            
            try {
                // SQLファイルを読み込み
                $sqlFile = __DIR__ . '/update_theme_colors_schema.sql';
                
                if (!file_exists($sqlFile)) {
                    throw new Exception("SQLファイルが見つかりません: $sqlFile");
                }
                
                $sqlContent = file_get_contents($sqlFile);
                if ($sqlContent === false) {
                    throw new Exception("SQLファイルの読み込みに失敗しました");
                }
                
                // データベース接続
                $pdo = getDatabase();
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 現在のデータベース構造をチェック
                $colorColumnExists = false;
                $userDepartmentColorsTableExists = false;
                
                try {
                    // departmentsテーブルのcolorカラム存在チェック
                    $stmt = $pdo->query("PRAGMA table_info(departments)");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($columns as $column) {
                        if ($column['name'] === 'color') {
                            $colorColumnExists = true;
                            break;
                        }
                    }
                    
                    // user_department_colorsテーブル存在チェック
                    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_department_colors'");
                    $userDepartmentColorsTableExists = $stmt->fetch() !== false;
                    
                } catch (Exception $e) {
                    $results[] = [
                        'type' => 'warning',
                        'sql' => 'Database structure check',
                        'message' => 'データベース構造チェック失敗: ' . $e->getMessage()
                    ];
                }
                
                $results[] = [
                    'type' => 'info',
                    'sql' => '',
                    'message' => sprintf('現在の状態: colorカラム=%s, user_department_colorsテーブル=%s', 
                        $colorColumnExists ? '存在' : '未存在',
                        $userDepartmentColorsTableExists ? '存在' : '未存在'
                    )
                ];
                
                // SQLを行ごとに分割して実行
                $sqlStatements = explode(';', $sqlContent);
                
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($sqlStatements as $statement) {
                    $statement = trim($statement);
                    
                    // 空の行やコメント行をスキップ
                    if (empty($statement) || strpos($statement, '--') === 0) {
                        continue;
                    }
                    
                    try {
                        // 特定の条件でスキップ処理
                        $shouldSkip = false;
                        $skipReason = '';
                        
                        // ALTER TABLE文でcolorカラムが既に存在する場合
                        if (strpos($statement, 'ALTER TABLE departments ADD COLUMN color') !== false && $colorColumnExists) {
                            $shouldSkip = true;
                            $skipReason = 'colorカラムは既に存在します';
                        }
                        
                        // user_department_colorsテーブルのインデックス作成で、テーブルが存在しない場合
                        if (strpos($statement, 'user_department_colors') !== false && 
                            strpos($statement, 'CREATE INDEX') !== false && 
                            !$userDepartmentColorsTableExists) {
                            $shouldSkip = true;
                            $skipReason = 'user_department_colorsテーブルが存在しないため、インデックス作成をスキップ';
                        }
                        
                        // UPDATE文でcolorカラムが存在しない場合
                        if (strpos($statement, 'UPDATE departments SET color') !== false && !$colorColumnExists) {
                            $shouldSkip = true;
                            $skipReason = 'colorカラムが存在しないため、UPDATE文をスキップ';
                        }
                        
                        if ($shouldSkip) {
                            $results[] = [
                                'type' => 'warning',
                                'sql' => $statement,
                                'message' => 'スキップ: ' . $skipReason
                            ];
                            continue;
                        }
                        
                        $stmt = $pdo->prepare($statement);
                        $stmt->execute();
                        
                        // 実行成功後の状態更新
                        if (strpos($statement, 'ALTER TABLE departments ADD COLUMN color') !== false) {
                            $colorColumnExists = true;
                        }
                        if (strpos($statement, 'CREATE TABLE IF NOT EXISTS user_department_colors') !== false) {
                            $userDepartmentColorsTableExists = true;
                        }
                        
                        $results[] = [
                            'type' => 'success',
                            'sql' => $statement,
                            'message' => '実行成功'
                        ];
                        $successCount++;
                        
                    } catch (PDOException $e) {
                        // エラーハンドリング
                        if (strpos($e->getMessage(), 'duplicate column name') !== false || 
                            strpos($e->getMessage(), 'table user_department_colors already exists') !== false ||
                            strpos($e->getMessage(), 'already exists') !== false ||
                            strpos($e->getMessage(), 'no such column: color') !== false ||
                            strpos($e->getMessage(), 'no such table') !== false) {
                            
                            $results[] = [
                                'type' => 'warning',
                                'sql' => $statement,
                                'message' => 'スキップ: ' . $e->getMessage()
                            ];
                        } else {
                            $results[] = [
                                'type' => 'error',
                                'sql' => $statement,
                                'message' => 'エラー: ' . $e->getMessage()
                            ];
                            $errorCount++;
                        }
                    }
                }
                
                // 実行結果サマリー
                if ($errorCount === 0) {
                    echo '<div class="status success">';
                    echo '<h3>✅ スキーマ更新完了</h3>';
                    echo '<p>すべてのSQL文が正常に実行されました。</p>';
                    echo '<p>成功: ' . $successCount . '件</p>';
                    echo '</div>';
                } else {
                    echo '<div class="status warning">';
                    echo '<h3>⚠️ スキーマ更新完了（一部エラーあり）</h3>';
                    echo '<p>成功: ' . $successCount . '件, エラー: ' . $errorCount . '件</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="status error">';
                echo '<h3>❌ スキーマ更新失敗</h3>';
                echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        
        if (!$executed) {
            // 実行前の確認画面
            echo '<div class="status info">';
            echo '<h3>📋 実行予定のSQL</h3>';
            echo '<p>以下のSQL文を実行してテーマカラー機能のデータベーススキーマを更新します：</p>';
            
            $sqlFile = __DIR__ . '/update_theme_colors_schema.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                echo '<div class="sql-content">' . htmlspecialchars($sqlContent) . '</div>';
                
                echo '<div class="status warning">';
                echo '<h4>⚠️ 注意事項</h4>';
                echo '<ul>';
                echo '<li>この操作はデータベースの構造を変更します</li>';
                echo '<li>既存のデータには影響しませんが、念のためバックアップを推奨します</li>';
                echo '<li>既にスキーマが更新されている場合、一部の操作はスキップされます</li>';
                echo '</ul>';
                echo '</div>';
                
                echo '<form method="post">';
                echo '<button type="submit" name="execute" class="btn">🚀 スキーマ更新を実行</button>';
                echo '</form>';
            } else {
                echo '<div class="status error">';
                echo '<p>SQLファイルが見つかりません: ' . htmlspecialchars($sqlFile) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            // 実行結果の詳細表示
            echo '<h3>📝 実行ログ</h3>';
            
            foreach ($results as $result) {
                $statusClass = $result['type'];
                echo "<div class='status $statusClass'>";
                echo "<h4>" . ucfirst($result['type']) . "</h4>";
                echo "<p><strong>メッセージ:</strong> " . htmlspecialchars($result['message']) . "</p>";
                echo "<p><strong>SQL:</strong></p>";
                echo "<pre>" . htmlspecialchars($result['sql']) . "</pre>";
                echo "</div>";
            }
            
            echo '<div style="margin-top: 30px;">';
            echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="btn">🔄 再実行</a>';
            echo '<a href="../admin/department_management.html" class="btn">🏢 部署管理へ</a>';
            echo '<a href="../index.html" class="btn">🏠 ホームへ</a>';
            echo '</div>';
        }
        ?>
        
        <hr style="margin: 30px 0;">
        
        <h3>🔧 データベース情報</h3>
        <?php
        try {
            $pdo = getDatabase();
            
            // データベースファイルの場所
            echo '<p><strong>データベースファイル:</strong> ' . htmlspecialchars(DB_PATH) . '</p>';
            
            // 現在のテーブル一覧
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<p><strong>現在のテーブル:</strong></p>';
            echo '<ul>';
            foreach ($tables as $table) {
                echo '<li>' . htmlspecialchars($table) . '</li>';
            }
            echo '</ul>';
            
            // departmentsテーブルの構造確認
            if (in_array('departments', $tables)) {
                echo '<p><strong>departmentsテーブルの構造:</strong></p>';
                $stmt = $pdo->query("PRAGMA table_info(departments)");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<pre>';
                foreach ($columns as $column) {
                    echo htmlspecialchars($column['name']) . ' (' . htmlspecialchars($column['type']) . ')' . "\n";
                }
                echo '</pre>';
            }
            
        } catch (Exception $e) {
            echo '<div class="status error">';
            echo '<p>データベース情報の取得に失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
    </div>
</body>
</html>