// コンフィグページ専用JavaScript

let currentUserData = null;

// ページ初期化
async function initConfigPage() {
    // 認証チェック
    const authStatus = await checkAuth();
    if (!authStatus.logged_in) {
        window.location.href = 'auth.html';
        return;
    }
    
    // ユーザー情報を表示用に更新
    updateUserDisplayInfo(authStatus.user);
    
    // イベントリスナー設定
    setupConfigEventListeners();
    
    // ユーザー情報を読み込み
    await loadUserData();
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

// ユーザーデータ読み込み
async function loadUserData() {
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
    const departmentInput = document.getElementById('user-department');
    const roleSelect = document.getElementById('user-role');
    
    if (nameInput) nameInput.value = userData.name || '';
    if (emailInput) emailInput.value = userData.email || '';
    if (departmentInput) departmentInput.value = userData.department || '';
    if (roleSelect) roleSelect.value = userData.role || 'user';
}

// プロフィール設定フォーム送信処理
async function handleUserConfigSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const userData = {
        name: formData.get('name')?.trim(),
        email: formData.get('email')?.trim(),
        department: formData.get('department')?.trim()
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
            showMessage('プロフィール設定を保存しました', 'success');
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

// ページ初期化
document.addEventListener('DOMContentLoaded', function() {
    initConfigPage();
});