<?php
/**
 * CSV取り込みプレビューAPI
 * CSVデータの検証とプレビューを行う
 */

// デバッグのため一時的にエラー出力を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// JSONレスポンスヘッダー設定
header('Content-Type: application/json; charset=utf-8');

// 認証チェック
requireAuth();

try {
    // POSTデータ取得
    $csvDataJson = $_POST['csv_data'] ?? null;
    $duplicateHandling = $_POST['duplicate_handling'] ?? 'skip';
    $validateConflicts = $_POST['validate_conflicts'] ?? '1';
    
    if (!$csvDataJson) {
        sendJsonResponse(false, 'CSVデータが送信されていません', null, 400);
        exit;
    }
    
    $csvData = json_decode($csvDataJson, true);
    if (!$csvData) {
        sendJsonResponse(false, 'CSVデータの解析に失敗しました', null, 400);
        exit;
    }
    
    $db = getDatabase();
    
    // バリデーション結果
    $validRows = 0;
    $errorCount = 0;
    $errors = [];
    $preview = [];
    
    // 既存ユーザーのメールアドレス一覧を取得
    $userEmails = [];
    $stmt = $db->prepare("SELECT email, id, name FROM users");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userEmails[strtolower($row['email'])] = $row;
    }
    
    // 各行を検証
    foreach ($csvData['rows'] as $index => $row) {
        $lineNumber = $row['_lineNumber'] ?? ($index + 2);
        $rowErrors = [];
        $isValid = true;
        
        // 必須フィールドチェック
        $requiredFields = ['title', 'date', 'start_time', 'end_time', 'user_email'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                $rowErrors[] = "{$field}は必須です";
                $isValid = false;
            }
        }
        
        // タイトル長さチェック
        if (!empty($row['title']) && mb_strlen($row['title']) > 50) {
            $rowErrors[] = "タイトルは50文字以内で入力してください";
            $isValid = false;
        }
        
        // 説明長さチェック
        if (!empty($row['description']) && mb_strlen($row['description']) > 400) {
            $rowErrors[] = "説明は400文字以内で入力してください";
            $isValid = false;
        }
        
        // 日付形式チェック
        if (!empty($row['date'])) {
            $dateObj = null;
            $normalizedDate = '';
            
            // 複数の日付形式に対応
            $dateFormats = [
                'Y-m-d',     // 2025-01-15
                'Y/m/d',     // 2025/1/15, 2025/10/16
                'Y/n/j',     // 2025/1/5
                'm/d/Y',     // 1/15/2025, 10/16/2025
                'n/j/Y'      // 1/5/2025
            ];
            
            foreach ($dateFormats as $format) {
                $dateObj = DateTime::createFromFormat($format, $row['date']);
                if ($dateObj && $dateObj->format($format) === $row['date']) {
                    $normalizedDate = $dateObj->format('Y-m-d');
                    break;
                }
            }
            
            // どの形式でもパースできない場合
            if (!$dateObj) {
                $rowErrors[] = "日付形式が正しくありません（YYYY-MM-DD、YYYY/MM/DD、MM/DD/YYYY形式で入力してください）";
                $isValid = false;
            } else {
                // 正規化された日付を行データに設定
                $row['date'] = $normalizedDate;
                
                // 過去の日付チェック
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                if ($dateObj < $today) {
                    $rowErrors[] = "過去の日付は指定できません";
                    $isValid = false;
                }
            }
        }
        
        // 時間形式チェック
        if (!empty($row['start_time'])) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $row['start_time'])) {
                $rowErrors[] = "開始時間の形式が正しくありません（HH:MM）";
                $isValid = false;
            }
        }
        
        if (!empty($row['end_time'])) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $row['end_time'])) {
                $rowErrors[] = "終了時間の形式が正しくありません（HH:MM）";
                $isValid = false;
            }
        }
        
        // 時間の論理チェック
        if (!empty($row['start_time']) && !empty($row['end_time'])) {
            $startTime = strtotime($row['start_time']);
            $endTime = strtotime($row['end_time']);
            
            if ($startTime >= $endTime) {
                $rowErrors[] = "終了時間は開始時間より後にしてください";
                $isValid = false;
            }
            
            // 営業時間チェック（9:00-18:00）
            $nineAM = strtotime('09:00');
            $sixPM = strtotime('18:00');
            
            if ($startTime < $nineAM || $endTime > $sixPM) {
                $rowErrors[] = "予約時間は9:00-18:00の範囲で指定してください";
                $isValid = false;
            }
        }
        
        // ユーザーメールアドレスチェック
        if (!empty($row['user_email'])) {
            if (!filter_var($row['user_email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "メールアドレスの形式が正しくありません";
                $isValid = false;
            } else {
                $emailKey = strtolower($row['user_email']);
                if (!isset($userEmails[$emailKey])) {
                    $rowErrors[] = "指定されたメールアドレスのユーザーが見つかりません";
                    $isValid = false;
                }
            }
        }
        
        // 繰り返しタイプチェック
        if (!empty($row['repeat_type'])) {
            $validRepeatTypes = ['daily', 'weekly', 'biweekly', 'monthly'];
            if (!in_array($row['repeat_type'], $validRepeatTypes)) {
                $rowErrors[] = "繰り返しタイプが無効です（daily, weekly, biweekly, monthly）";
                $isValid = false;
            }
            
            // 繰り返し終了日チェック
            if (!empty($row['repeat_end_date'])) {
                $endDateObj = null;
                $normalizedEndDate = '';
                
                // 複数の日付形式に対応
                foreach ($dateFormats as $format) {
                    $endDateObj = DateTime::createFromFormat($format, $row['repeat_end_date']);
                    if ($endDateObj && $endDateObj->format($format) === $row['repeat_end_date']) {
                        $normalizedEndDate = $endDateObj->format('Y-m-d');
                        break;
                    }
                }
                
                if (!$endDateObj) {
                    $rowErrors[] = "繰り返し終了日の形式が正しくありません（YYYY-MM-DD、YYYY/MM/DD、MM/DD/YYYY形式で入力してください）";
                    $isValid = false;
                } else {
                    // 正規化された日付を行データに設定
                    $row['repeat_end_date'] = $normalizedEndDate;
                    
                    // 開始日より後かチェック
                    if (!empty($row['date'])) {
                        $startDateObj = DateTime::createFromFormat('Y-m-d', $row['date']);
                        if ($startDateObj && $endDateObj <= $startDateObj) {
                            $rowErrors[] = "繰り返し終了日は開始日より後にしてください";
                            $isValid = false;
                        }
                    }
                }
            }
        }
        
        // 時間重複チェック（有効化されている場合）
        if ($validateConflicts === '1' && $isValid && !empty($row['date']) && !empty($row['start_time']) && !empty($row['end_time'])) {
            $startDateTime = $row['date'] . ' ' . $row['start_time'] . ':00';
            $endDateTime = $row['date'] . ' ' . $row['end_time'] . ':00';
            
            // 重複チェックのクエリ
            $conflictStmt = $db->prepare("
                SELECT COUNT(*) as count FROM reservations 
                WHERE date = ? 
                AND (
                    (start_datetime < ? AND end_datetime > ?) OR
                    (start_datetime < ? AND end_datetime > ?) OR
                    (start_datetime >= ? AND end_datetime <= ?)
                )
            ");
            $conflictStmt->execute([
                $row['date'],
                $startDateTime, $startDateTime,
                $endDateTime, $endDateTime,
                $startDateTime, $endDateTime
            ]);
            
            $conflictResult = $conflictStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conflictResult && $conflictResult['count'] > 0) {
                $rowErrors[] = "指定された時間帯に既存の予約があります";
                $isValid = false;
            }
        }
        
        // エラー記録
        if (!$isValid) {
            $errorCount++;
            foreach ($rowErrors as $error) {
                $errors[] = [
                    'line' => $lineNumber,
                    'message' => $error
                ];
            }
        } else {
            $validRows++;
        }
        
        // プレビューデータ作成（正規化後のデータを使用）
        $preview[] = [
            'lineNumber' => $lineNumber,
            'hasError' => !$isValid,
            'title' => $row['title'] ?? '',
            'date' => $row['date'] ?? '', // 正規化された日付
            'start_time' => $row['start_time'] ?? '',
            'end_time' => $row['end_time'] ?? '',
            'user_email' => $row['user_email'] ?? '',
            'description' => $row['description'] ?? '',
            'errors' => $rowErrors
        ];
        
        // 元の配列も更新（取り込み時に使用）
        $csvData['rows'][$index] = $row;
    }
    
    // レスポンスデータ
    $responseData = [
        'totalRows' => count($csvData['rows']),
        'validRows' => $validRows,
        'errorCount' => $errorCount,
        'errors' => $errors,
        'preview' => $preview,
        'csvData' => $csvData // 取り込み時に使用
    ];
    
    sendJsonResponse(true, 'プレビューを生成しました', $responseData);
    
} catch (Exception $e) {
    error_log("CSV Import Preview Error: " . $e->getMessage());
    sendJsonResponse(false, 'プレビューの生成に失敗しました: ' . $e->getMessage(), null, 500);
}
?>