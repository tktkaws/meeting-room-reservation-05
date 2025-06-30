<?php

/**
 * 部署テーマカラー管理API
 */

// 出力バッファリングを開始してエラーメッセージの混入を防ぐ
ob_start();

// エラー表示を抑制
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// セッションが既に開始されていない場合のみ開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// バッファをクリアしてからヘッダーを送信
ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // department_theme_colorsカラムが存在するかチェック
    $check_sql = "PRAGMA table_info(users)";
    $stmt = $pdo->query($check_sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $column_exists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'department_theme_colors') {
            $column_exists = true;
            break;
        }
    }

    if (!$column_exists) {
        // カラムが存在しない場合はデフォルトカラーを返す
        $default_colors = [
            "1" => "#46556B",
            "2" => "#11A444",
            "3" => "#F3373F",
            "4" => "#616161"
        ];
        echo json_encode(['status' => 'success', 'colors' => $default_colors]);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // テーマカラー取得
            $user_id = $_SESSION['user_id'] ?? null;

            if (!$user_id) {
                // 非ログインユーザーにはデフォルトカラーを返す
                $default_colors = [
                    "1" => "#46556B",
                    "2" => "#11A444",
                    "3" => "#F3373F",
                    "4" => "#616161"
                ];
                echo json_encode(['status' => 'success', 'colors' => $default_colors]);
                exit;
            }

            $sql = "SELECT department_theme_colors FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['department_theme_colors']) {
                $colors = json_decode($user['department_theme_colors'], true);
            } else {
                // デフォルトカラー
                $colors = [
                    "1" => "#46556B",
                    "2" => "#11A444",
                    "3" => "#F3373F",
                    "4" => "#616161"
                ];
            }

            echo json_encode(['status' => 'success', 'colors' => $colors]);
            break;

        case 'POST':
        case 'PUT':
            // テーマカラー更新（ログインユーザー）
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'ログインが必要です']);
                exit;
            }

            $user_id = $_SESSION['user_id'];

            // POSTメソッドの場合はFormDataも対応
            if ($method === 'POST' && isset($_POST['department_id']) && isset($_POST['color'])) {
                $department_id = $_POST['department_id'];
                $color = $_POST['color'];

                // 既存のカラー設定を取得
                $sql = "SELECT department_theme_colors FROM users WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $colors = [];
                if ($user && $user['department_theme_colors']) {
                    $colors = json_decode($user['department_theme_colors'], true) ?: [];
                }

                // 指定された部署のカラーを更新
                $colors[$department_id] = $color;
            } else {
                // PUT/JSON形式の場合
                $input = json_decode(file_get_contents('php://input'), true);
                $colors = $input['colors'] ?? null;

                if (!$colors || !is_array($colors)) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'カラー情報が無効です']);
                    exit;
                }
            }

            // カラーコード形式チェック
            foreach ($colors as $dept_id => $color) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => '無効なカラーコード: ' . $color]);
                    exit;
                }
            }

            // データベース更新
            $colors_json = json_encode($colors);
            $sql = "UPDATE users SET department_theme_colors = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$colors_json, $user_id]);

            echo json_encode(['status' => 'success', 'message' => 'テーマカラーを更新しました']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    error_log("Database error in theme_colors.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'データベースエラーが発生しました']);
} catch (Exception $e) {
    error_log("Error in theme_colors.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'エラーが発生しました']);
}
