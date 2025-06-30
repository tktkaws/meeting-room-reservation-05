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
            // ホームページに遷移
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('ログアウトエラー:', error);
    }
}

// ユーザー情報更新
function updateUserInfo() {
    if (!currentUser) {
        console.warn('updateUserInfo called without currentUser');
        return;
    }
    
    // console.log('updateUserInfo called with user:', currentUser);
    const userInfo = document.getElementById('user-info');
    if (userInfo) {
        // 部署IDから部署名を取得するために部署データをロード
        loadUserInfoWithDepartment();
    }
    
    // サイドバーのユーザー情報も同時に更新
    updateSidebarUserInfo();
}

// サイドバーのユーザー情報を更新する専用関数
function updateSidebarUserInfo() {
    if (!currentUser) return;
    
    const sidebarUserInfo = document.getElementById('sidebar-user-info');
    if (sidebarUserInfo) {
        // console.log('Updating sidebar with user:', currentUser.name);
        sidebarUserInfo.textContent = currentUser.name || 'ユーザー名不明';
    }
}

// 部署名付きでユーザー情報を更新
async function loadUserInfoWithDepartment() {
    try {
        // 部署データを取得
        const deptResponse = await fetch('api/departments.php');
        const deptData = await deptResponse.json();
        
        let departmentName = '部署未設定';
        if (deptData.success && currentUser.department) {
            const dept = deptData.departments.find(d => d.id == currentUser.department);
            if (dept) {
                departmentName = dept.name;
            }
        }
        
        const userInfo = document.getElementById('user-info');
        if (userInfo) {
            userInfo.textContent = `${currentUser.name} (${departmentName})`;
        }
    } catch (error) {
        console.error('部署情報の取得に失敗しました:', error);
        const userInfo = document.getElementById('user-info');
        if (userInfo && currentUser) {
            userInfo.textContent = `${currentUser.name} (部署未設定)`;
        }
    }
}

// セッションストレージからユーザー情報を更新（設定ページから戻った時用）
function refreshUserInfoFromSession() {
    if (typeof Storage !== 'undefined') {
        try {
            const authData = JSON.parse(sessionStorage.getItem('authData') || '{}');
            if (authData.user && authData.timestamp && currentUser) {
                // セッションストレージのデータが現在のcurrentUserより新しい場合のみ更新
                const sessionTimestamp = authData.timestamp || 0;
                const currentTimestamp = currentUser.lastUpdated || 0;
                
                if (sessionTimestamp > currentTimestamp) {
                    console.log('セッションストレージから新しいユーザー情報を更新:', authData.user);
                    currentUser = { ...authData.user, lastUpdated: sessionTimestamp };
                    updateUserInfo();
                    // サイドバーも確実に更新
                    if (typeof updateSidebarVisibility === 'function') {
                        updateSidebarVisibility();
                    }
                }
            }
        } catch (error) {
            console.warn('セッションストレージからの更新に失敗しました:', error);
        }
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