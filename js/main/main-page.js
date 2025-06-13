// メインページ専用エントリーポイント

// メインページの初期化
async function initMainPage() {
    // 認証チェック
    const authStatus = await checkAuth();
    const isLoggedIn = authStatus.logged_in;
    
    if (isLoggedIn) {
        currentUser = authStatus.user;
        updateUserInfo();
    } else {
        // 非ログインユーザー向けUI設定
        setupGuestUserUI();
    }
    
    // イベントリスナー設定
    setupEventListeners(isLoggedIn);
    
    // 保存されたビュー状態を復元（ログイン状態に関係なく）
    await restoreSavedView();
    
    // カレンダー初期表示
    await loadReservations();
    renderCalendar();
}

// 非ログインユーザー向けUI設定
function setupGuestUserUI() {
    // ユーザー情報表示を非表示
    const userInfo = document.getElementById('user-info');
    if (userInfo) {
        userInfo.style.display = 'none';
    }
    
    // ログアウトボタンを非表示にしてログインボタンに変更
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.textContent = 'ログイン';
        logoutBtn.onclick = () => window.location.href = 'auth.html';
    }
    
    // 設定ボタンを非表示
    const configBtn = document.getElementById('config-btn');
    if (configBtn) {
        configBtn.style.display = 'none';
    }
    
    // 新規予約ボタンを非表示
    const newReservationBtn = document.getElementById('new-reservation-btn');
    if (newReservationBtn) {
        newReservationBtn.style.display = 'none';
    }
}

// 保存されたビュー状態を復元
async function restoreSavedView() {
    const savedView = loadSavedView();
    currentView = savedView;
    
    // ボタンのアクティブ状態を更新
    document.querySelectorAll('.btn-view').forEach(btn => btn.classList.remove('active'));
    const targetBtn = document.getElementById(`${savedView}-view`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }
    
    // リスト表示の場合は今日以降の全予約データを読み込み
    if (savedView === 'list') {
        await loadAllFutureReservations();
    }
}

// イベントリスナー設定
function setupEventListeners(isLoggedIn = false) {
    // ナビゲーションボタン（非ログインでも利用可能）
    document.getElementById('prev-btn')?.addEventListener('click', () => navigateDate(-1));
    document.getElementById('next-btn')?.addEventListener('click', () => navigateDate(1));
    document.getElementById('today-btn')?.addEventListener('click', goToToday);
    
    // ビュー切り替え（非ログインでも利用可能）
    document.getElementById('month-view')?.addEventListener('click', () => switchView('month'));
    document.getElementById('week-view')?.addEventListener('click', () => switchView('week'));
    document.getElementById('list-view')?.addEventListener('click', async () => await switchView('list'));
    document.getElementById('day-view')?.addEventListener('click', () => switchView('day'));
    
    // 詳細表示モーダル（非ログインでも利用可能、編集機能は制限）
    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    document.getElementById('reservation-detail-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailModal();
        }
    });
    
    // ログインユーザーのみの機能
    if (isLoggedIn) {
        // ログアウト・設定ボタン
        document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
        document.getElementById('config-btn')?.addEventListener('click', () => {
            window.location.href = 'config.html';
        });
        
        // 新規予約ボタン
        document.getElementById('new-reservation-btn')?.addEventListener('click', openNewReservationModal);
        
        // モーダル関連
        document.getElementById('close-modal')?.addEventListener('click', closeModal);
        document.getElementById('cancel-btn')?.addEventListener('click', closeModal);
        document.getElementById('close-group-edit-modal')?.addEventListener('click', closeGroupEditModal);
        document.getElementById('group-cancel-btn')?.addEventListener('click', closeGroupEditModal);
        
        // 予約フォーム送信
        document.getElementById('reservation-form')?.addEventListener('submit', handleReservationSubmit);
        
        // グループ編集フォーム送信
        document.getElementById('group-edit-form')?.addEventListener('submit', handleGroupEditSubmit);
        
        // 繰り返し予約ラジオボタン
        document.getElementById('recurring-yes')?.addEventListener('change', toggleRecurringOptions);
        document.getElementById('recurring-no')?.addEventListener('change', toggleRecurringOptions);
        
        // モーダル外クリックで閉じる
        document.getElementById('reservation-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('group-edit-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeGroupEditModal();
            }
        });
    }
}

// ページ初期化
document.addEventListener('DOMContentLoaded', function() {
    initMainPage();
});