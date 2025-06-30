// コンフィグページ専用JavaScript

let currentUserData = null;

// ページ初期化
async function initConfigPage() {
    // 共通ヘッダーを更新
    await updateCommonHeader();
    
    // 認証チェック
    const authStatus = await checkAuth();
    if (!authStatus.logged_in) {
        // モーダル環境では認証ページに遷移しない
        if (typeof openAuthModal === 'function') {
            console.warn('Config page initialized without authentication');
            return;
        } else {
            window.location.href = 'auth.html';
            return;
        }
    }
    
    // ユーザー情報を表示用に更新
    updateUserDisplayInfo(authStatus.user);
    
    // イベントリスナー設定
    setupConfigEventListeners();
    
    // 部署データを読み込み
    await loadDepartments();
    
    // ユーザー情報を読み込み
    await loadUserData();
    
    // テーマカラー設定を読み込み（管理者の場合）
    await loadThemeColorSettings();
}

// イベントリスナー設定
function setupConfigEventListeners() {
    // メインページへ戻る
    document.getElementById('back-to-main')?.addEventListener('click', () => {
        window.location.href = 'index.html';
    });
    
    // ログアウト
    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
    
    // プロフィール設定フォーム
    document.getElementById('user-config-form')?.addEventListener('submit', handleUserConfigSubmit);
    
    // パスワード変更フォーム
    document.getElementById('password-change-form')?.addEventListener('submit', handlePasswordChangeSubmit);
    
    // テーマカラー設定フォーム
    document.getElementById('theme-color-form')?.addEventListener('submit', handleThemeColorSubmit);
    
    // キャンセルボタン
    document.getElementById('cancel-btn')?.addEventListener('click', resetUserForm);
    document.getElementById('cancel-password-btn')?.addEventListener('click', resetPasswordForm);
}

// ユーザー表示情報更新
function updateUserDisplayInfo(user) {
    const userDisplay = document.getElementById('user-display-name');
    if (userDisplay) {
        userDisplay.textContent = user.name || 'ユーザー';
    }
}

// ヘッダーのユーザー情報更新
function updateHeaderUserInfo(user) {
    const userDisplay = document.getElementById('user-display-name');
    if (userDisplay) {
        userDisplay.textContent = user.name || 'ユーザー';
    }
    
    // グローバルcurrentUserを更新
    if (typeof currentUser !== 'undefined') {
        currentUser = { ...user, lastUpdated: Date.now() };
        console.log('updateHeaderUserInfo: Updated currentUser to:', currentUser);
        
        // サイドバーのユーザー情報を更新
        if (typeof updateSidebarUserInfo === 'function') {
            updateSidebarUserInfo();
        }
    }
    
    // セッションストレージも更新（他のページで使用される可能性があるため）
    if (typeof Storage !== 'undefined') {
        try {
            const authData = JSON.parse(sessionStorage.getItem('authData') || '{}');
            authData.user = { ...user, lastUpdated: Date.now() };
            authData.timestamp = Date.now();
            sessionStorage.setItem('authData', JSON.stringify(authData));
            console.log('updateHeaderUserInfo: Updated sessionStorage with:', authData);
        } catch (error) {
            console.warn('セッションストレージの更新に失敗しました:', error);
        }
    }
}

// 部署データ読み込み
async function loadDepartments() {
    try {
        const response = await fetch('api/departments.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            populateDepartmentSelect(data.departments);
        } else {
            console.error('部署データの読み込みに失敗しました:', data.message);
        }
        
    } catch (error) {
        console.error('部署データ読み込みエラー:', error);
    }
}

// 部署セレクトボックスにデータを設定
function populateDepartmentSelect(departments) {
    const departmentSelect = document.getElementById('user-department');
    if (!departmentSelect) return;
    
    // 既存のオプションをクリア
    departmentSelect.innerHTML = '';
    
    // 部署オプションを追加
    departments.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept.id;
        option.textContent = dept.name;
        departmentSelect.appendChild(option);
    });
}

// ユーザーデータ読み込み
async function loadUserData() {
    // 認証チェック
    if (!currentUser) {
        console.warn('loadUserData called without authentication');
        return;
    }
    
    try {
        showLoading();
        
        const response = await fetch('api/user.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUserData = data.data;
            populateUserForm(currentUserData);
        } else {
            showMessage(data.message || 'ユーザー情報の読み込みに失敗しました', 'error');
        }
        
    } catch (error) {
        console.error('ユーザーデータ読み込みエラー:', error);
        showMessage('ユーザー情報の読み込み中にエラーが発生しました', 'error');
    } finally {
        hideLoading();
    }
}

// ユーザーフォームにデータを設定
function populateUserForm(userData) {
    const nameInput = document.getElementById('user-name');
    const emailInput = document.getElementById('user-email');
    const departmentSelect = document.getElementById('user-department');
    const roleSelect = document.getElementById('user-role');
    const emailNotificationSelect = document.getElementById('email-notification-type');
    
    if (nameInput) nameInput.value = userData.name || '';
    if (emailInput) emailInput.value = userData.email || '';
    if (departmentSelect) departmentSelect.value = userData.department || '1';
    if (roleSelect) roleSelect.value = userData.role || 'user';
    if (emailNotificationSelect) emailNotificationSelect.value = userData.email_notification_type || 2;
}

// プロフィール設定フォーム送信処理
async function handleUserConfigSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const userData = {
        name: formData.get('name')?.trim(),
        email: formData.get('email')?.trim(),
        department: formData.get('department')?.trim(),
        email_notification_type: parseInt(formData.get('email_notification_type')) || 2
    };
    
    // クライアントサイドバリデーション
    if (!userData.name) {
        showMessage('名前は必須です', 'error');
        return;
    }
    
    if (!userData.email) {
        showMessage('メールアドレスは必須です', 'error');
        return;
    }
    
    if (!isValidEmail(userData.email)) {
        showMessage('有効なメールアドレスを入力してください', 'error');
        return;
    }
    
    try {
        showLoading();
        
        const response = await fetch('api/user.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(userData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUserData = data.data;
            populateUserForm(currentUserData);
            updateUserDisplayInfo(currentUserData);
            updateHeaderUserInfo(currentUserData);
            showMessage('プロフィール設定を保存しました', 'success');
            
            // ヘッダーを更新
            setTimeout(async () => {
                if (typeof updateCommonHeader === 'function') {
                    await updateCommonHeader();
                }
            }, 100);
        } else {
            showMessage(data.message || 'プロフィール設定の保存に失敗しました', 'error');
        }
        
    } catch (error) {
        console.error('プロフィール設定エラー:', error);
        showMessage('プロフィール設定の保存中にエラーが発生しました', 'error');
    } finally {
        hideLoading();
    }
}

// パスワード変更フォーム送信処理
async function handlePasswordChangeSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const passwordData = {
        current_password: formData.get('current_password'),
        new_password: formData.get('new_password'),
        confirm_password: formData.get('confirm_password')
    };
    
    // クライアントサイドバリデーション
    if (!passwordData.current_password) {
        showMessage('現在のパスワードは必須です', 'error');
        return;
    }
    
    if (!passwordData.new_password) {
        showMessage('新しいパスワードは必須です', 'error');
        return;
    }
    
    if (passwordData.new_password.length < 6) {
        showMessage('新しいパスワードは6文字以上で入力してください', 'error');
        return;
    }
    
    if (passwordData.new_password !== passwordData.confirm_password) {
        showMessage('新しいパスワードが一致しません', 'error');
        return;
    }
    
    try {
        showLoading();
        
        const response = await fetch('api/password.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(passwordData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            resetPasswordForm();
            showMessage('パスワードを変更しました', 'success');
        } else {
            showMessage(data.message || 'パスワードの変更に失敗しました', 'error');
        }
        
    } catch (error) {
        console.error('パスワード変更エラー:', error);
        showMessage('パスワード変更中にエラーが発生しました', 'error');
    } finally {
        hideLoading();
    }
}


// ユーザーフォームリセット
function resetUserForm() {
    if (currentUserData) {
        populateUserForm(currentUserData);
    }
}

// パスワードフォームリセット
function resetPasswordForm() {
    const form = document.getElementById('password-change-form');
    if (form) {
        form.reset();
    }
}


// メールアドレスバリデーション
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// ローディング表示
function showLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = 'flex';
    }
}

// ローディング非表示
function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = 'none';
    }
}

// メッセージ表示
function showMessage(message, type = 'success') {
    const messageElement = document.getElementById('message');
    if (messageElement) {
        messageElement.textContent = message;
        messageElement.className = `message ${type}`;
        messageElement.classList.add('show');
        
        setTimeout(() => {
            messageElement.classList.remove('show');
        }, 3000);
    }
}

// テーマカラー設定読み込み
async function loadThemeColorSettings() {
    try {
        // 認証状態を確認
        const authStatus = await checkAuth();
        if (!authStatus.logged_in) {
            // 非ログインユーザーはテーマカラー設定を非表示にする
            const themeSection = document.getElementById('theme-color-section');
            if (themeSection) {
                themeSection.style.display = 'none';
            }
            return;
        }
        
        // ログインユーザーはセクションを表示
        const themeSection = document.getElementById('theme-color-section');
        if (themeSection) {
            themeSection.style.display = 'block';
        }
        
        // 部署データと現在のテーマカラーを取得
        const [departmentsResponse, colorsResponse] = await Promise.all([
            fetch('api/departments.php', { credentials: 'same-origin' }),
            fetch('api/theme_colors.php', { credentials: 'same-origin' })
        ]);
        
        const departmentsData = await departmentsResponse.json();
        const colorsData = await colorsResponse.json();
        
        if (departmentsData.success && colorsData.status === 'success') {
            setupThemeColorControls(departmentsData.departments, colorsData.colors);
        }
        
    } catch (error) {
        console.error('テーマカラー設定読み込みエラー:', error);
    }
}

// テーマカラー設定コントロールを生成
function setupThemeColorControls(departments, currentColors) {
    const container = document.getElementById('theme-color-controls');
    if (!container) return;
    
    container.innerHTML = '';
    
    departments.forEach(dept => {
        const controlGroup = document.createElement('div');
        controlGroup.className = 'form-group theme-color-group';
        
        const label = document.createElement('label');
        label.textContent = `${dept.name}`;
        label.setAttribute('for', `theme-color-${dept.id}`);
        
        const colorInput = document.createElement('input');
        colorInput.type = 'color';
        colorInput.id = `theme-color-${dept.id}`;
        colorInput.name = `color_${dept.id}`;
        colorInput.value = currentColors[dept.id] || '#4299E1';
        colorInput.className = 'theme-color-input';
        
        const colorPreview = document.createElement('div');
        colorPreview.className = 'color-preview';
        colorPreview.style.backgroundColor = colorInput.value;
        
        // カラー変更時にプレビューを更新とリアルタイム反映
        colorInput.addEventListener('change', (e) => {
            colorPreview.style.backgroundColor = e.target.value;
            // リアルタイムでテーマカラーを更新
            updateThemeColorRealtime(dept.id, e.target.value);
        });
        
        // リアルタイムプレビュー（input イベント）
        colorInput.addEventListener('input', (e) => {
            colorPreview.style.backgroundColor = e.target.value;
            // リアルタイムでテーマカラーを更新
            updateThemeColorRealtime(dept.id, e.target.value);
        });
        
        const inputWrapper = document.createElement('div');
        inputWrapper.className = 'color-input-wrapper';
        inputWrapper.appendChild(colorInput);
        inputWrapper.appendChild(colorPreview);
        
        controlGroup.appendChild(label);
        controlGroup.appendChild(inputWrapper);
        container.appendChild(controlGroup);
    });
}

// テーマカラー設定フォーム送信処理
async function handleThemeColorSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const colors = {};
    
    // フォームデータからカラー情報を抽出
    for (const [key, value] of formData.entries()) {
        if (key.startsWith('color_')) {
            const deptId = key.replace('color_', '');
            colors[deptId] = value;
        }
    }
    
    try {
        showLoading();
        
        const response = await fetch('api/theme_colors.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ colors })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showMessage('テーマカラーを保存しました', 'success');
            
            // テーマカラーを再読み込みして適用
            await loadDepartmentThemeColors();
            
            // 即座にテーマカラーを適用
            if (typeof applyDepartmentThemeColors === 'function') {
                applyDepartmentThemeColors();
            }
            
            // 他のタブにも通知（保存完了後）
            const formData = new FormData(event.target);
            for (const [key, value] of formData.entries()) {
                if (key.startsWith('color_')) {
                    const deptId = key.replace('color_', '');
                    try {
                        const themeUpdate = {
                            type: 'themeColorUpdate',
                            deptId: deptId,
                            color: value,
                            timestamp: Date.now()
                        };
                        localStorage.setItem('themeColorUpdate', JSON.stringify(themeUpdate));
                        setTimeout(() => {
                            localStorage.removeItem('themeColorUpdate');
                        }, 100);
                    } catch (e) {
                        console.warn('LocalStorage更新失敗:', e);
                    }
                }
            }
        } else {
            showMessage(data.message || 'テーマカラーの保存に失敗しました', 'error');
        }
        
    } catch (error) {
        console.error('テーマカラー保存エラー:', error);
        showMessage('テーマカラーの保存中にエラーが発生しました', 'error');
    } finally {
        hideLoading();
    }
}

// リアルタイムテーマカラー更新
function updateThemeColorRealtime(deptId, color) {
    // departmentColors オブジェクトを更新
    if (typeof departmentColors !== 'undefined') {
        departmentColors[String(deptId)] = color;
    }
    
    // 特定の部署のみカラー適用（最適化）
    if (typeof applyColorToDepartment === 'function') {
        applyColorToDepartment(deptId, color);
    } else if (typeof applyDepartmentThemeColors === 'function') {
        // フォールバック: 全体適用
        applyDepartmentThemeColors();
    }
    
    // LocalStorage を使用して他のタブに通知
    try {
        const themeUpdate = {
            type: 'themeColorUpdate',
            deptId: deptId,
            color: color,
            timestamp: Date.now()
        };
        localStorage.setItem('themeColorUpdate', JSON.stringify(themeUpdate));
        // 即座にLocalStorageから削除（イベント発火のみが目的）
        setTimeout(() => {
            localStorage.removeItem('themeColorUpdate');
        }, 100);
    } catch (e) {
        console.warn('LocalStorage使用不可:', e);
    }
    
    // 別ウィンドウ/タブに通知（可能であれば）
    try {
        if (window.opener && !window.opener.closed) {
            // 親ウィンドウがある場合は通知
            window.opener.postMessage({
                type: 'themeColorUpdate',
                deptId: deptId,
                color: color
            }, '*');
        }
    } catch (e) {
        // エラーは無視
    }
}

// 他のウィンドウからのメッセージを受信
window.addEventListener('message', function(event) {
    if (event.data.type === 'themeColorUpdate') {
        // departmentColors を更新
        if (typeof departmentColors !== 'undefined') {
            departmentColors[String(event.data.deptId)] = event.data.color;
        }
        
        // テーマカラーを適用
        if (typeof applyDepartmentThemeColors === 'function') {
            applyDepartmentThemeColors();
        }
    }
});

// ページ初期化
document.addEventListener('DOMContentLoaded', function() {
    initConfigPage();
});