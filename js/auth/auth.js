// 認証機能
let currentUser = null;

// 認証状態チェック
async function checkAuth() {
    try {
        const response = await fetch('api/auth.php');
        const result = await response.json();
        
        // 新しいAPIレスポンス形式に対応
        if (result.success && result.data) {
            return result.data;
        } else {
            return { logged_in: false };
        }
    } catch (error) {
        console.error('認証チェックエラー:', error);
        return { logged_in: false };
    }
}

// ログイン処理
async function handleLogin() {
    const formData = new FormData(document.getElementById('loginForm'));
    formData.append('action', 'login');
    
    try {
        showLoading(true);
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('ログインしました', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        } else {
            showMessage(result.message || 'ログインに失敗しました', 'error');
        }
    } catch (error) {
        console.error('ログインエラー:', error);
        showMessage('ログインに失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}

// ユーザー登録処理
async function handleRegister() {
    const formData = new FormData(document.getElementById('registerForm'));
    formData.append('action', 'register');
    
    try {
        showLoading(true);
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('ユーザー登録が完了しました。ログインしてください。', 'success');
            // ログインフォームに切り替え
            document.getElementById('register-form').style.display = 'none';
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('registerForm').reset();
        } else {
            showMessage(result.message || 'ユーザー登録に失敗しました', 'error');
        }
    } catch (error) {
        console.error('登録エラー:', error);
        showMessage('ユーザー登録に失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}

// ログアウト処理
async function handleLogout() {
    try {
        const formData = new FormData();
        formData.append('action', 'logout');
        
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = 'auth.html';
        }
    } catch (error) {
        console.error('ログアウトエラー:', error);
    }
}

// ユーザー情報更新
function updateUserInfo() {
    const userInfo = document.getElementById('user-info');
    if (userInfo && currentUser) {
        userInfo.textContent = `${currentUser.name} (${currentUser.department || '部署未設定'})`;
    }
}

// 認証ページの初期化
function initAuthPage() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const showRegisterBtn = document.getElementById('show-register');
    const showLoginBtn = document.getElementById('show-login');
    
    // フォーム切り替え
    showRegisterBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
        clearMessage();
    });
    
    showLoginBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('login-form').style.display = 'block';
        clearMessage();
    });
    
    // ログインフォーム送信
    loginForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        handleLogin();
    });
    
    // 登録フォーム送信
    registerForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        handleRegister();
    });
}