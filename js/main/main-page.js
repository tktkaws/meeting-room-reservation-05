// メインページ専用エントリーポイント

// メインページの初期化
async function initMainPage() {
    // 認証チェック
    const authStatus = await checkAuth();
    if (!authStatus.logged_in) {
        window.location.href = 'auth.html';
        return;
    }
    
    currentUser = authStatus.user;
    updateUserInfo();
    
    // イベントリスナー設定
    setupEventListeners();
    
    // カレンダー初期表示
    await loadReservations();
    renderCalendar();
}

// イベントリスナー設定
function setupEventListeners() {
    // ログアウトボタン
    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
    
    // ナビゲーションボタン
    document.getElementById('prev-btn')?.addEventListener('click', () => navigateDate(-1));
    document.getElementById('next-btn')?.addEventListener('click', () => navigateDate(1));
    document.getElementById('today-btn')?.addEventListener('click', goToToday);
    
    // ビュー切り替え
    document.getElementById('month-view')?.addEventListener('click', () => switchView('month'));
    document.getElementById('week-view')?.addEventListener('click', () => switchView('week'));
    document.getElementById('list-view')?.addEventListener('click', async () => await switchView('list'));
    document.getElementById('day-view')?.addEventListener('click', () => switchView('day'));
    
    // 新規予約ボタン
    document.getElementById('new-reservation-btn')?.addEventListener('click', openNewReservationModal);
    
    // モーダル関連
    document.getElementById('close-modal')?.addEventListener('click', closeModal);
    document.getElementById('cancel-btn')?.addEventListener('click', closeModal);
    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
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
    
    document.getElementById('reservation-detail-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailModal();
        }
    });
    
    document.getElementById('group-edit-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeGroupEditModal();
        }
    });
}

// ページ初期化
document.addEventListener('DOMContentLoaded', function() {
    initMainPage();
});