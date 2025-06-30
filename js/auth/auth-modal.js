// 認証モーダル機能

// ログイン後のユーザー情報更新
async function updateUserInfoAfterLogin() {
    try {
        // 認証状態を再取得
        const authStatus = await checkAuth();
        if (authStatus.logged_in) {
            // グローバルのcurrentUserを更新
            currentUser = authStatus.user;
            
            // ヘッダーのユーザー情報を更新
            updateUserInfo();
            
            // サイドバーの表示状態を更新
            if (typeof updateSidebarVisibility === 'function') {
                updateSidebarVisibility();
            }
            
            // メインページのログインユーザー向けUI設定
            if (typeof setupMainPageUserUI === 'function') {
                setupMainPageUserUI();
            }
            
            // カレンダーの再読み込み（編集権限情報などを反映）
            if (typeof loadReservations === 'function' && typeof renderCalendar === 'function') {
                await loadReservations();
                renderCalendar();
            }
        }
    } catch (error) {
        console.error('ログイン後のユーザー情報更新エラー:', error);
    }
}

// 認証モーダルを開く
function openAuthModal(mode = 'login') {
    const modal = document.getElementById('auth-modal');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const modalTitle = document.getElementById('auth-modal-title');
    
    // フォームをリセット
    document.getElementById('loginForm').reset();
    document.getElementById('registerForm').reset();
    
    if (mode === 'login') {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
        modalTitle.textContent = 'ログイン';
    } else if (mode === 'register') {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
        modalTitle.textContent = '新規登録';
    }
    
    modal.style.display = 'flex';
    clearMessage();
}

// 認証モーダルを閉じる
function closeAuthModal() {
    const modal = document.getElementById('auth-modal');
    modal.style.display = 'none';
    
    // フォームをリセット
    document.getElementById('loginForm').reset();
    document.getElementById('registerForm').reset();
    clearMessage();
}

// ログイン処理（モーダル用に修正）
async function handleModalLogin() {
    const formData = new FormData(document.getElementById('loginForm'));
    formData.append('action', 'login');
    
    try {
        // ローディング表示（直接DOM操作）
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('ログインレスポンス:', responseText);
        console.log('レスポンスヘッダー:', response.headers.get('content-type'));
        console.log('ステータスコード:', response.status);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON解析エラー:', parseError);
            console.error('レスポンステキスト:', responseText);
            console.error('レスポンス長:', responseText.length);
            console.error('最初の100文字:', responseText.substring(0, 100));
            showMessage('サーバーエラーが発生しました。ブラウザのコンソールをご確認ください。', 'error');
            return;
        }
        
        if (result.success) {
            showMessage('ログインしました', 'success');
            closeAuthModal();
            
            // ユーザー情報を更新
            await updateUserInfoAfterLogin();
            
            // 少し遅延を入れてメッセージを見せる
            setTimeout(() => {
                // メッセージをクリア
                clearMessage();
            }, 2000);
        } else {
            showMessage(result.message || 'ログインに失敗しました', 'error');
        }
    } catch (error) {
        console.error('ログインエラー:', error);
        showMessage('ログインに失敗しました', 'error');
    } finally {
        // ローディングを確実に停止（直接DOM操作）
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// ユーザー登録処理（モーダル用に修正）
async function handleModalRegister() {
    const formData = new FormData(document.getElementById('registerForm'));
    formData.append('action', 'register');
    
    try {
        // ローディング表示（直接DOM操作）
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('登録レスポンス:', responseText);
        console.log('レスポンスヘッダー:', response.headers.get('content-type'));
        console.log('ステータスコード:', response.status);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON解析エラー:', parseError);
            console.error('レスポンステキスト:', responseText);
            console.error('レスポンス長:', responseText.length);
            console.error('最初の100文字:', responseText.substring(0, 100));
            showMessage('サーバーエラーが発生しました。ブラウザのコンソールをご確認ください。', 'error');
            return;
        }
        
        if (result.success) {
            showMessage('ユーザー登録が完了しました。ログインしてください。', 'success');
            // ログインフォームに切り替え
            document.getElementById('register-form').style.display = 'none';
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('auth-modal-title').textContent = 'ログイン';
            document.getElementById('registerForm').reset();
        } else {
            showMessage(result.message || 'ユーザー登録に失敗しました', 'error');
        }
    } catch (error) {
        console.error('登録エラー:', error);
        showMessage('ユーザー登録に失敗しました', 'error');
    } finally {
        // ローディングを確実に停止（直接DOM操作）
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// 認証モーダルの初期化
function initAuthModal() {
    const showRegisterBtn = document.getElementById('show-register');
    const showLoginBtn = document.getElementById('show-login');
    const closeModalBtn = document.getElementById('close-auth-modal');
    const cancelAuthBtn = document.getElementById('cancel-auth-btn');
    const cancelRegisterBtn = document.getElementById('cancel-register-btn');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    // フォーム切り替え
    showRegisterBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
        document.getElementById('auth-modal-title').textContent = '新規登録';
        clearMessage();
    });
    
    showLoginBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('auth-modal-title').textContent = 'ログイン';
        clearMessage();
    });
    
    // モーダルを閉じる
    closeModalBtn?.addEventListener('click', closeAuthModal);
    cancelAuthBtn?.addEventListener('click', closeAuthModal);
    cancelRegisterBtn?.addEventListener('click', closeAuthModal);
    
    // モーダル外クリックで閉じる（ドラッグ操作を考慮）
    document.getElementById('auth-modal')?.addEventListener('mousedown', function(e) {
        if (e.target === this) {
            const startX = e.clientX;
            const startY = e.clientY;
            
            const handleMouseUp = (upEvent) => {
                const endX = upEvent.clientX;
                const endY = upEvent.clientY;
                const distance = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
                
                // ドラッグ距離が5px以下の場合のみクリックとみなす
                if (distance <= 5 && upEvent.target === this) {
                    closeAuthModal();
                }
                
                document.removeEventListener('mouseup', handleMouseUp);
            };
            
            document.addEventListener('mouseup', handleMouseUp);
        }
    });
    
    // ログインフォーム送信
    loginForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        handleModalLogin();
    });
    
    // 登録フォーム送信
    registerForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        handleModalRegister();
    });
}