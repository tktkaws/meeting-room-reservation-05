<?php
require_once 'config.php';
require_once 'security.php';

header('Content-Type: application/json');

// ユーザーのテーマカラーを取得
function getUserThemeColor($userId, $departmentId) {
    $pdo = getDatabase();
    try {
        // まず user_department_colors テーブルから個別設定を確認
        $stmt = $pdo->prepare("SELECT color FROM user_department_colors WHERE user_id = ? AND department_id = ?");
        $stmt->execute([$userId, $departmentId]);
        $userColor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userColor) {
            return [
                'success' => true, 
                'color' => $userColor['color'],
                'source' => 'user',
                'message' => 'ユーザー個別カラーを取得しました'
            ];
        }
        
        // 個別設定がない場合はデフォルトカラーを取得
        $stmt = $pdo->prepare("SELECT color FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $deptColor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($deptColor) {
            return [
                'success' => true, 
                'color' => $deptColor['color'] ?? '#718096',
                'source' => 'department',
                'message' => 'デフォルトカラーを取得しました'
            ];
        }
        
        return [
            'success' => true, 
            'color' => '#718096',
            'source' => 'default',
            'message' => 'システムデフォルトカラーを使用します'
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

// ユーザーのテーマカラーを保存
function saveUserThemeColor($userId, $departmentId, $color) {
    $pdo = getDatabase();
    try {
        // 既存レコードをチェック
        $stmt = $pdo->prepare("SELECT id FROM user_department_colors WHERE user_id = ? AND department_id = ?");
        $stmt->execute([$userId, $departmentId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // 更新
            $stmt = $pdo->prepare("UPDATE user_department_colors SET color = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND department_id = ?");
            $stmt->execute([$color, $userId, $departmentId]);
            return ['success' => true, 'message' => 'テーマカラーを更新しました', 'action' => 'updated'];
        } else {
            // 新規挿入
            $stmt = $pdo->prepare("INSERT INTO user_department_colors (user_id, department_id, color) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $departmentId, $color]);
            return ['success' => true, 'message' => 'テーマカラーを保存しました', 'action' => 'created'];
        }
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

// ユーザーのテーマカラー設定を削除（デフォルトに戻す）
function resetUserThemeColor($userId, $departmentId) {
    $pdo = getDatabase();
    try {
        // ユーザー設定を削除
        $stmt = $pdo->prepare("DELETE FROM user_department_colors WHERE user_id = ? AND department_id = ?");
        $stmt->execute([$userId, $departmentId]);
        
        // departmentsテーブルからデフォルトカラーを取得
        $stmt = $pdo->prepare("SELECT color FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $deptColor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $defaultColor = $deptColor ? ($deptColor['color'] ?? '#718096') : '#718096';
        
        return [
            'success' => true, 
            'message' => 'ユーザー設定を削除しました。部署のデフォルトカラーに戻ります。',
            'color' => $defaultColor,
            'source' => 'department'
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

// リクエスト処理
$method = $_SERVER['REQUEST_METHOD'];

// 認証チェック
if (!isset($_SESSION)) {
    session_start();
}

// デバッグログ
error_log("user_theme_colors.php: Method=$method, Session=" . json_encode($_SESSION ?? []));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '認証が必要です']);
    exit;
}

$currentUserId = $_SESSION['user_id'];

switch ($method) {
    case 'GET':
        $departmentId = $_GET['department_id'] ?? null;
        $userId = $_GET['user_id'] ?? $currentUserId;
        
        error_log("user_theme_colors.php GET: departmentId=$departmentId, userId=$userId, currentUserId=$currentUserId");
        
        // 管理者以外は自分のデータのみアクセス可能
        if ($userId != $currentUserId && ($_SESSION['role'] ?? '') !== 'admin') {
            error_log("user_theme_colors.php GET: Access denied");
            echo json_encode(['success' => false, 'message' => 'アクセス権限がありません']);
            break;
        }
        
        if (!$departmentId) {
            error_log("user_theme_colors.php GET: Department ID missing");
            echo json_encode(['success' => false, 'message' => '部署IDが必要です']);
            break;
        }
        
        error_log("user_theme_colors.php GET: Calling getUserThemeColor");
        $result = getUserThemeColor($userId, $departmentId);
        error_log("user_theme_colors.php GET: Result=" . json_encode($result));
        echo json_encode($result);
        break;
        
    case 'POST':
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        error_log("user_theme_colors.php POST: Raw input=" . $rawInput);
        error_log("user_theme_colors.php POST: Parsed input=" . json_encode($input));
        
        if (!$input) {
            echo json_encode(['success' => false, 'message' => '無効なJSONデータです']);
            break;
        }
        
        $action = $input['action'] ?? '';
        $departmentId = $input['department_id'] ?? null;
        $userId = $input['user_id'] ?? $currentUserId;
        $color = $input['color'] ?? null;
        
        error_log("user_theme_colors.php POST: action=$action, departmentId=$departmentId, userId=$userId, color=$color");
        
        // 管理者以外は自分のデータのみ変更可能
        if ($userId != $currentUserId && ($_SESSION['role'] ?? '') !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'アクセス権限がありません']);
            break;
        }
        
        if (!$departmentId) {
            echo json_encode(['success' => false, 'message' => '部署IDが必要です']);
            break;
        }
        
        switch ($action) {
            case 'save':
                if (!$color) {
                    echo json_encode(['success' => false, 'message' => 'カラーが必要です']);
                    break;
                }
                echo json_encode(saveUserThemeColor($userId, $departmentId, $color));
                break;
                
            case 'reset':
                echo json_encode(resetUserThemeColor($userId, $departmentId));
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '無効なアクションです']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'サポートされていないHTTPメソッドです']);
}
?>