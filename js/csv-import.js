// CSV取り込みページ機能

let selectedFile = null;
let previewData = null;

// ページ初期化
async function initCSVImportPage(skipAuthCheck = false) {
    if (!skipAuthCheck) {
        // 認証チェック（正しいAPIエンドポイントを使用）
        try {
            const response = await fetch('../api/check_auth.php');
            const authStatus = await response.json();
            
            if (!authStatus.logged_in) {
                // ログインしていない場合はトップページにリダイレクト
                window.location.href = '../index.html';
                return;
            }
            
            // ユーザー情報を設定
            currentUser = { 
                id: authStatus.user_id,
                name: authStatus.session_data?.name || 'ユーザー名不明',
                role: authStatus.session_data?.role || 'user',
                department: authStatus.session_data?.department || null,
                lastUpdated: Date.now()
            };
            
            await updateCommonHeader();
        } catch (error) {
            console.error('認証チェックエラー:', error);
            window.location.href = '../index.html';
            return;
        }
    }
    
    // イベントリスナー設定
    setupEventListeners();
}

// イベントリスナー設定
function setupEventListeners() {
    // ファイル選択関連
    const fileInput = document.getElementById('file-input');
    const fileSelectBtn = document.getElementById('file-select-btn');
    const fileUploadArea = document.getElementById('file-upload-area');
    const fileRemoveBtn = document.getElementById('file-remove-btn');
    
    // ファイル選択ボタン
    fileSelectBtn.addEventListener('click', () => {
        fileInput.click();
    });
    
    // ファイル選択時
    fileInput.addEventListener('change', handleFileSelect);
    
    // ドラッグ&ドロップ
    fileUploadArea.addEventListener('dragover', handleDragOver);
    fileUploadArea.addEventListener('drop', handleFileDrop);
    fileUploadArea.addEventListener('click', () => {
        if (!selectedFile) {
            fileInput.click();
        }
    });
    
    // ファイル削除
    fileRemoveBtn.addEventListener('click', removeFile);
    
    // プレビューボタン
    document.getElementById('preview-btn').addEventListener('click', handlePreview);
    
    // 取り込みボタン
    document.getElementById('import-btn').addEventListener('click', handleImport);
    
    // サンプルダウンロード
    document.getElementById('download-sample-btn').addEventListener('click', downloadSample);
}

// ドラッグオーバー処理
function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

// ファイルドロップ処理
function handleFileDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
}

// ファイル選択処理
function handleFileSelect(e) {
    const files = e.target.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
}

// ファイル処理
function handleFile(file) {
    // ファイル形式チェック
    const allowedTypes = ['text/csv', 'text/tab-separated-values', 'application/vnd.ms-excel'];
    const fileName = file.name.toLowerCase();
    
    if (!allowedTypes.includes(file.type) && !fileName.endsWith('.csv') && !fileName.endsWith('.tsv')) {
        showMessage('CSVまたはTSVファイルを選択してください', 'error');
        return;
    }
    
    // ファイルサイズチェック（10MB制限）
    if (file.size > 10 * 1024 * 1024) {
        showMessage('ファイルサイズは10MB以下にしてください', 'error');
        return;
    }
    
    selectedFile = file;
    showFileInfo(file);
    
    // プレビューボタンを有効化
    document.getElementById('preview-btn').disabled = false;
}

// ファイル情報表示
function showFileInfo(file) {
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const uploadArea = document.getElementById('file-upload-area');
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    uploadArea.style.display = 'none';
    fileInfo.style.display = 'flex';
}

// ファイル削除
function removeFile() {
    selectedFile = null;
    previewData = null;
    
    const fileInfo = document.getElementById('file-info');
    const uploadArea = document.getElementById('file-upload-area');
    const previewSection = document.getElementById('preview-section');
    
    fileInfo.style.display = 'none';
    uploadArea.style.display = 'flex';
    previewSection.style.display = 'none';
    
    // ボタンを無効化
    document.getElementById('preview-btn').disabled = true;
    document.getElementById('import-btn').disabled = true;
    
    // ファイル入力をリセット
    document.getElementById('file-input').value = '';
}

// ファイルサイズフォーマット
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// プレビュー処理
async function handlePreview() {
    if (!selectedFile) {
        showMessage('ファイルを選択してください', 'error');
        return;
    }
    
    showLoading(true);
    
    try {
        // ファイルを読み込み
        const csvText = await readFileAsText(selectedFile);
        
        // CSVパース
        const parseResult = parseCSV(csvText);
        
        if (!parseResult.success) {
            showMessage(parseResult.error, 'error');
            return;
        }
        
        // プレビューAPI呼び出し
        const previewResult = await callPreviewAPI(parseResult.data);
        
        if (previewResult && previewResult.success) {
            previewData = previewResult.data;
            showPreview(previewResult.data);
            
            // 取り込みボタンを有効化（エラーがない場合）
            document.getElementById('import-btn').disabled = previewResult.data.errorCount > 0;
        } else {
            const errorMessage = previewResult ? (previewResult.error || 'プレビューに失敗しました') : 'APIレスポンスが無効です';
            showMessage(errorMessage, 'error');
        }
    } catch (error) {
        console.error('Preview error:', error);
        showMessage('プレビュー処理中にエラーが発生しました', 'error');
    } finally {
        showLoading(false);
    }
}

// ファイルをテキストとして読み込み
function readFileAsText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => resolve(e.target.result);
        reader.onerror = e => reject(e);
        reader.readAsText(file, 'UTF-8');
    });
}

// CSV解析
function parseCSV(csvText) {
    try {
        const fileFormat = document.querySelector('input[name="file_format"]:checked').value;
        const delimiter = fileFormat === 'tsv' ? '\t' : ',';
        
        const lines = csvText.trim().split('\n');
        if (lines.length < 2) {
            return { success: false, error: 'CSVファイルが空またはヘッダーのみです' };
        }
        
        // ヘッダー行の解析
        const headers = lines[0].split(delimiter).map(h => h.trim().replace(/"/g, ''));
        
        // 必須列のチェック
        const requiredColumns = ['title', 'date', 'start_time', 'end_time', 'user_email'];
        const missingColumns = requiredColumns.filter(col => !headers.includes(col));
        
        if (missingColumns.length > 0) {
            return { 
                success: false, 
                error: `必須列が不足しています: ${missingColumns.join(', ')}` 
            };
        }
        
        // データ行の解析
        const data = [];
        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(delimiter).map(v => v.trim().replace(/"/g, ''));
            const row = {};
            
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            
            row._lineNumber = i + 1;
            data.push(row);
        }
        
        return { success: true, data: { headers, rows: data } };
    } catch (error) {
        return { success: false, error: 'CSVファイルの解析に失敗しました: ' + error.message };
    }
}

// プレビューAPI呼び出し
async function callPreviewAPI(csvData) {
    const formData = new FormData();
    formData.append('csv_data', JSON.stringify(csvData));
    formData.append('duplicate_handling', document.querySelector('input[name="duplicate_handling"]:checked').value);
    formData.append('validate_conflicts', document.getElementById('validate_conflicts').checked ? '1' : '0');
    
    const response = await fetch('../api/preview_csv_import.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        const errorText = await response.text();
        console.error('API Error Response:', errorText);
        throw new Error(`HTTP error! status: ${response.status} - ${errorText.substring(0, 500)}`);
    }
    
    const result = await response.json();
    return result;
}

// プレビュー表示
function showPreview(data) {
    const previewSection = document.getElementById('preview-section');
    const previewStats = document.getElementById('preview-stats');
    const previewTable = document.getElementById('preview-table');
    const errorList = document.getElementById('error-list');
    
    // 統計情報更新
    document.getElementById('total-rows').textContent = data.totalRows;
    document.getElementById('valid-rows').textContent = data.validRows;
    document.getElementById('error-rows').textContent = data.errorCount;
    
    // プレビューテーブル更新
    updatePreviewTable(data);
    
    // エラー詳細表示
    if (data.errors && data.errors.length > 0) {
        showErrors(data.errors);
        errorList.style.display = 'block';
    } else {
        errorList.style.display = 'none';
    }
    
    previewSection.style.display = 'block';
}

// プレビューテーブル更新
function updatePreviewTable(data) {
    const previewHeader = document.getElementById('preview-header');
    const previewBody = document.getElementById('preview-body');
    
    // ヘッダー作成
    const headerRow = document.createElement('tr');
    headerRow.innerHTML = `
        <th>行</th>
        <th>状態</th>
        <th>タイトル</th>
        <th>日付</th>
        <th>時間</th>
        <th>予約者</th>
        <th>説明</th>
    `;
    previewHeader.innerHTML = '';
    previewHeader.appendChild(headerRow);
    
    // データ行作成（最大20行まで表示）
    previewBody.innerHTML = '';
    const maxRows = Math.min(data.preview.length, 20);
    
    for (let i = 0; i < maxRows; i++) {
        const row = data.preview[i];
        const tr = document.createElement('tr');
        tr.className = row.hasError ? 'error-row' : 'valid-row';
        
        tr.innerHTML = `
            <td>${row.lineNumber}</td>
            <td>
                <span class="status-badge ${row.hasError ? 'error' : 'valid'}">
                    ${row.hasError ? 'エラー' : 'OK'}
                </span>
            </td>
            <td>${row.title || ''}</td>
            <td>${row.date || ''}</td>
            <td>${row.start_time || ''} - ${row.end_time || ''}</td>
            <td>${row.user_email || ''}</td>
            <td>${(row.description || '').substring(0, 30)}${(row.description || '').length > 30 ? '...' : ''}</td>
        `;
        
        previewBody.appendChild(tr);
    }
    
    if (data.preview.length > 20) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="7" class="more-rows">他 ${data.preview.length - 20} 行...</td>`;
        previewBody.appendChild(tr);
    }
}

// エラー詳細表示
function showErrors(errors) {
    const errorItems = document.getElementById('error-items');
    errorItems.innerHTML = '';
    
    errors.forEach(error => {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-item';
        errorDiv.innerHTML = `
            <div class="error-line">行 ${error.line}:</div>
            <div class="error-message">${error.message}</div>
        `;
        errorItems.appendChild(errorDiv);
    });
}

// 取り込み実行
async function handleImport() {
    if (!previewData || previewData.errorCount > 0) {
        showMessage('エラーがあるため取り込みできません', 'error');
        return;
    }
    
    if (!confirm('取り込みを実行しますか？この操作は取り消せません。')) {
        return;
    }
    
    // 進行状況モーダル表示
    showProgressModal();
    
    try {
        const formData = new FormData();
        formData.append('csv_data', JSON.stringify(previewData.csvData));
        formData.append('duplicate_handling', document.querySelector('input[name="duplicate_handling"]:checked').value);
        formData.append('validate_conflicts', document.getElementById('validate_conflicts').checked ? '1' : '0');
        formData.append('send_notifications', document.getElementById('send_notifications').checked ? '1' : '0');
        
        const response = await fetch('../api/import_csv_reservations.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            hideProgressModal();
            showMessage(`取り込み完了: ${result.data.imported}件の予約を登録しました`, 'success');
            
            // フォームをリセット
            setTimeout(() => {
                removeFile();
            }, 2000);
        } else {
            hideProgressModal();
            showMessage(result.error || '取り込みに失敗しました', 'error');
        }
    } catch (error) {
        console.error('Import error:', error);
        hideProgressModal();
        showMessage('取り込み処理中にエラーが発生しました', 'error');
    }
}

// 進行状況モーダル表示
function showProgressModal() {
    const modal = document.getElementById('progress-modal');
    modal.style.display = 'flex';
    
    // 進行状況の更新（簡易版）
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 10;
        if (progress > 90) progress = 90;
        
        const progressBar = document.getElementById('progress-bar');
        progressBar.style.width = progress + '%';
    }, 500);
    
    // モーダルにintervalを保存
    modal._interval = interval;
}

// 進行状況モーダル非表示
function hideProgressModal() {
    const modal = document.getElementById('progress-modal');
    modal.style.display = 'none';
    
    if (modal._interval) {
        clearInterval(modal._interval);
        modal._interval = null;
    }
    
    // 進行状況リセット
    document.getElementById('progress-bar').style.width = '0%';
}

// サンプルファイルダウンロード
function downloadSample() {
    const sampleData = [
        ['title', 'date', 'start_time', 'end_time', 'user_email', 'description', 'repeat_type', 'repeat_end_date'],
        ['定例会議', '2025-01-15', '09:00', '10:00', 'user@example.com', '毎週の定例会議', 'weekly', '2025-03-31'],
        ['プロジェクト打ち合わせ', '2025/1/16', '14:00', '15:30', 'manager@example.com', 'プロジェクトの進捗確認', '', ''],
        ['研修', '1/20/2025', '10:00', '17:00', 'training@example.com', '新人研修', '', ''],
        ['月次会議', '2025/10/16', '13:00', '14:00', 'admin@example.com', '月次レビュー会議', '', '']
    ];
    
    const csvContent = sampleData.map(row => 
        row.map(cell => `"${cell}"`).join(',')
    ).join('\n');
    
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'sample_reservations.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ローディング表示制御
function showLoading(show) {
    const loading = document.getElementById('loading');
    loading.style.display = show ? 'flex' : 'none';
}