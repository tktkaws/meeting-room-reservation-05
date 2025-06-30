// CSV出力ページ機能

// ページ初期化
async function initCSVExportPage(skipAuthCheck = false) {
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
    
    // デフォルトで今月を設定
    setDefaultDates();
    
    // イベントリスナー設定
    setupEventListeners();
}

// デフォルト日付設定（今月）
function setDefaultDates() {
    const now = new Date();
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    document.getElementById('start-date').value = formatDateForInput(startOfMonth);
    document.getElementById('end-date').value = formatDateForInput(endOfMonth);
}

// 日付をinput[type="date"]用にフォーマット
function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

// イベントリスナー設定
function setupEventListeners() {
    // クイック選択ボタン
    document.querySelectorAll('.btn-quick').forEach(btn => {
        btn.addEventListener('click', handleQuickSelect);
    });
    
    // プレビューボタン
    document.getElementById('preview-btn').addEventListener('click', handlePreview);
    
    // 出力ボタン
    document.getElementById('csv-export-form').addEventListener('submit', handleExport);
    
    // 日付変更時のプレビュー自動更新
    document.getElementById('start-date').addEventListener('change', handleDateChange);
    document.getElementById('end-date').addEventListener('change', handleDateChange);
}

// クイック選択処理
function handleQuickSelect(e) {
    const period = e.target.dataset.period;
    const now = new Date();
    let startDate, endDate;
    
    switch (period) {
        case 'this-month':
            startDate = new Date(now.getFullYear(), now.getMonth(), 1);
            endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            break;
            
        case 'last-month':
            startDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            endDate = new Date(now.getFullYear(), now.getMonth(), 0);
            break;
            
        case 'this-quarter':
            const currentQuarter = Math.floor(now.getMonth() / 3);
            startDate = new Date(now.getFullYear(), currentQuarter * 3, 1);
            endDate = new Date(now.getFullYear(), (currentQuarter + 1) * 3, 0);
            break;
            
        case 'last-quarter':
            const lastQuarter = Math.floor(now.getMonth() / 3) - 1;
            const year = lastQuarter < 0 ? now.getFullYear() - 1 : now.getFullYear();
            const quarter = lastQuarter < 0 ? 3 : lastQuarter;
            startDate = new Date(year, quarter * 3, 1);
            endDate = new Date(year, (quarter + 1) * 3, 0);
            break;
            
        case 'this-year':
            startDate = new Date(now.getFullYear(), 0, 1);
            endDate = new Date(now.getFullYear(), 11, 31);
            break;
            
        case 'all':
            startDate = new Date(2024, 0, 1); // 2024年1月1日から
            endDate = new Date(now.getFullYear() + 1, 11, 31); // 来年末まで
            break;
    }
    
    document.getElementById('start-date').value = formatDateForInput(startDate);
    document.getElementById('end-date').value = formatDateForInput(endDate);
    
    // プレビューを自動更新
    handlePreview();
}

// 日付変更時の処理
function handleDateChange() {
    // プレビューをクリア
    const previewSection = document.getElementById('preview-section');
    previewSection.style.display = 'none';
}

// プレビュー処理
async function handlePreview() {
    const formData = getFormData();
    
    if (!validateForm(formData)) {
        return;
    }
    
    showLoading(true);
    
    try {
        // プレビューAPI呼び出し
        const url = `api/preview_csv_export.php?${new URLSearchParams(formData)}`;
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            showPreview(result.data);
        } else {
            showMessage(result.error || 'プレビューの取得に失敗しました', 'error');
        }
    } catch (error) {
        console.error('Preview error:', error);
        showMessage('プレビューの取得中にエラーが発生しました', 'error');
    } finally {
        showLoading(false);
    }
}

// 出力処理
async function handleExport(e) {
    e.preventDefault();
    
    const formData = getFormData();
    
    if (!validateForm(formData)) {
        return;
    }
    
    showLoading(true);
    
    try {
        // CSV出力API呼び出し
        const url = `api/export_reservations_csv.php?${new URLSearchParams(formData)}`;
        
        // ファイルダウンロード
        const link = document.createElement('a');
        link.href = url;
        const filename = `reservations_${formData.start_date}_to_${formData.end_date}.${formData.format}`;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showMessage('ファイルのダウンロードを開始しました', 'success');
    } catch (error) {
        console.error('Export error:', error);
        showMessage('出力中にエラーが発生しました', 'error');
    } finally {
        showLoading(false);
    }
}

// フォームデータ取得
function getFormData() {
    const form = document.getElementById('csv-export-form');
    const formData = new FormData(form);
    const data = {};
    
    // 基本データ
    data.start_date = formData.get('start_date');
    data.end_date = formData.get('end_date');
    data.format = formData.get('format') || 'csv';
    
    // チェックボックスデータ
    data.include_single = formData.get('include_single') ? '1' : '0';
    data.include_recurring = formData.get('include_recurring') ? '1' : '0';
    data.include_future = formData.get('include_future') ? '1' : '0';
    data.include_past = formData.get('include_past') ? '1' : '0';
    
    return data;
}

// フォームバリデーション
function validateForm(data) {
    if (!data.start_date || !data.end_date) {
        showMessage('開始日と終了日を入力してください', 'error');
        return false;
    }
    
    if (new Date(data.start_date) > new Date(data.end_date)) {
        showMessage('開始日は終了日以前にしてください', 'error');
        return false;
    }
    
    if (data.include_single === '0' && data.include_recurring === '0') {
        showMessage('少なくとも一つの予約タイプを選択してください', 'error');
        return false;
    }
    
    if (data.include_future === '0' && data.include_past === '0') {
        showMessage('少なくとも一つの予約状態を選択してください', 'error');
        return false;
    }
    
    return true;
}

// プレビュー表示
function showPreview(data) {
    const previewSection = document.getElementById('preview-section');
    const previewCount = document.getElementById('preview-count');
    const previewRange = document.getElementById('preview-range');
    
    previewCount.textContent = `${data.count}件`;
    
    const startDate = new Date(data.start_date);
    const endDate = new Date(data.end_date);
    const startStr = `${startDate.getFullYear()}年${startDate.getMonth() + 1}月${startDate.getDate()}日`;
    const endStr = `${endDate.getFullYear()}年${endDate.getMonth() + 1}月${endDate.getDate()}日`;
    
    previewRange.textContent = `期間: ${startStr} ～ ${endStr}`;
    
    previewSection.style.display = 'block';
}

// ローディング表示制御
function showLoading(show) {
    const loading = document.getElementById('loading');
    loading.style.display = show ? 'flex' : 'none';
}