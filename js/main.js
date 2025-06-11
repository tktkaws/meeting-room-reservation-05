// グローバル変数
let currentUser = null;
let currentView = 'month';
let currentDate = new Date();
let reservations = [];

// ページ読み込み時の初期化
document.addEventListener('DOMContentLoaded', function() {
    // 現在のページが認証ページかどうかチェック
    if (window.location.pathname.includes('auth.html')) {
        initAuthPage();
    } else {
        initMainPage();
    }
});

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

// 認証状態チェック
async function checkAuth() {
    try {
        const response = await fetch('api/auth.php');
        return await response.json();
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
            showMessage(result.error || 'ログインに失敗しました', 'error');
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
            showMessage(result.error || 'ユーザー登録に失敗しました', 'error');
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
    document.getElementById('day-view')?.addEventListener('click', () => switchView('day'));
    
    // 新規予約ボタン
    document.getElementById('new-reservation-btn')?.addEventListener('click', openNewReservationModal);
    
    // モーダル関連
    document.getElementById('close-modal')?.addEventListener('click', closeModal);
    document.getElementById('cancel-btn')?.addEventListener('click', closeModal);
    
    // 予約フォーム送信
    document.getElementById('reservation-form')?.addEventListener('submit', handleReservationSubmit);
    
    // 繰り返し予約チェックボックス
    document.getElementById('is-recurring')?.addEventListener('change', toggleRecurringOptions);
    
    // モーダル外クリックで閉じる
    document.getElementById('reservation-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

// ユーザー情報更新
function updateUserInfo() {
    const userInfo = document.getElementById('user-info');
    if (userInfo && currentUser) {
        userInfo.textContent = `${currentUser.name} (${currentUser.department || '部署未設定'})`;
    }
}

// 予約データ読み込み
async function loadReservations() {
    try {
        const startDate = formatDate(getMonthStart(currentDate));
        const endDate = formatDate(getMonthEnd(currentDate));
        
        console.log('予約データを読み込み中:', startDate, 'から', endDate);
        
        const response = await fetch(`api/reservations.php?start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        console.log('読み込み結果:', result);
        
        if (result.reservations) {
            reservations = result.reservations;
            console.log('予約データ:', reservations.length, '件');
        } else {
            reservations = [];
            console.log('予約データが空です');
        }
    } catch (error) {
        console.error('予約データ読み込みエラー:', error);
        showMessage('予約データの読み込みに失敗しました', 'error');
        reservations = [];
    }
}

// カレンダー表示
function renderCalendar() {
    const calendarView = document.getElementById('calendar-view');
    if (!calendarView) return;
    
    updateCurrentPeriod();
    
    if (currentView === 'month') {
        renderMonthView(calendarView);
    } else if (currentView === 'week') {
        renderWeekView(calendarView);
    } else if (currentView === 'day') {
        renderDayView(calendarView);
    }
}

// 月表示
function renderMonthView(container) {
    const monthStart = getMonthStart(currentDate);
    const monthEnd = getMonthEnd(currentDate);
    const calendarStart = getWeekStart(new Date(monthStart));
    const calendarEnd = getWeekEnd(new Date(monthEnd));
    
    let html = '<div class="calendar-grid">';
    
    // ヘッダー（曜日）
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    weekdays.forEach(day => {
        html += `<div class="calendar-header">${day}</div>`;
    });
    
    // 日付セル
    let currentDay = new Date(calendarStart);
    while (currentDay <= calendarEnd) {
        const dayReservations = getDayReservations(currentDay);
        const isToday = isSameDay(currentDay, new Date());
        const isCurrentMonth = currentDay.getMonth() === currentDate.getMonth();
        
        html += `
            <div class="calendar-day ${isToday ? 'today' : ''} ${!isCurrentMonth ? 'other-month' : ''}" 
                 data-date="${formatDate(currentDay)}" onclick="selectDate('${formatDate(currentDay)}')">
                <div class="day-number">${currentDay.getDate()}</div>
                <div class="reservations">
                    ${dayReservations.map(res => `
                        <div class="reservation-item" onclick="event.stopPropagation(); editReservation(${res.id})" title="${res.title} (${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)})">
                            <div class="reservation-title">${res.title}</div>
                            <div class="reservation-time">${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        currentDay.setDate(currentDay.getDate() + 1);
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// 週表示（簡易版）
function renderWeekView(container) {
    container.innerHTML = '<div class="week-view"><p>週表示は今後実装予定です</p></div>';
}

// 日表示（簡易版）
function renderDayView(container) {
    container.innerHTML = '<div class="day-view"><p>日表示は今後実装予定です</p></div>';
}

// 現在の期間表示更新
function updateCurrentPeriod() {
    const periodElement = document.getElementById('current-period');
    if (!periodElement) return;
    
    if (currentView === 'month') {
        periodElement.textContent = `${currentDate.getFullYear()}年 ${currentDate.getMonth() + 1}月`;
    }
}

// 日付ナビゲーション
function navigateDate(direction) {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + direction);
    }
    loadReservations().then(() => renderCalendar());
}

// 今日に移動
function goToToday() {
    currentDate = new Date();
    loadReservations().then(() => renderCalendar());
}

// ビュー切り替え
function switchView(view) {
    currentView = view;
    
    // ボタンのアクティブ状態更新
    document.querySelectorAll('.btn-view').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`${view}-view`).classList.add('active');
    
    renderCalendar();
}

// 新規予約モーダルを開く
function openNewReservationModal(selectedDate = null) {
    const modal = document.getElementById('reservation-modal');
    const form = document.getElementById('reservation-form');
    const deleteBtn = document.getElementById('delete-btn');
    
    // フォームリセット
    form.reset();
    form.removeAttribute('data-edit-id');
    document.getElementById('modal-title').textContent = '新規予約';
    
    // 削除ボタンを非表示
    if (deleteBtn) {
        deleteBtn.style.display = 'none';
    }
    
    // 選択された日付を設定
    if (selectedDate) {
        document.getElementById('reservation-date').value = selectedDate;
    } else {
        // 今日の日付を設定
        document.getElementById('reservation-date').value = formatDate(new Date());
    }
    
    modal.style.display = 'flex';
}

// 日付選択
function selectDate(dateStr) {
    openNewReservationModal(dateStr);
}

// 予約編集
async function editReservation(reservationId) {
    const reservation = reservations.find(r => r.id == reservationId);
    if (!reservation) {
        showMessage('予約が見つかりません', 'error');
        return;
    }
    
    // 権限チェック
    if (reservation.user_id != currentUser.id && currentUser.role !== 'admin') {
        showMessage('この予約を編集する権限がありません', 'error');
        return;
    }
    
    // 編集モーダルを開く
    const modal = document.getElementById('reservation-modal');
    const form = document.getElementById('reservation-form');
    
    document.getElementById('modal-title').textContent = '予約編集';
    document.getElementById('reservation-title').value = reservation.title;
    document.getElementById('reservation-description').value = reservation.description || '';
    document.getElementById('reservation-date').value = reservation.date;
    document.getElementById('start-time').value = reservation.start_datetime.split(' ')[1].substring(0,5);
    document.getElementById('end-time').value = reservation.end_datetime.split(' ')[1].substring(0,5);
    
    // フォームに編集用のIDを設定
    form.setAttribute('data-edit-id', reservationId);
    
    // 削除ボタンを追加
    let deleteBtn = document.getElementById('delete-btn');
    if (!deleteBtn) {
        deleteBtn = document.createElement('button');
        deleteBtn.id = 'delete-btn';
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn-danger';
        deleteBtn.textContent = '削除';
        deleteBtn.onclick = () => deleteReservation(reservationId);
        document.querySelector('.modal-actions').insertBefore(deleteBtn, document.getElementById('cancel-btn'));
    }
    deleteBtn.style.display = 'inline-block';
    
    modal.style.display = 'flex';
}

// 予約削除
async function deleteReservation(reservationId) {
    if (!confirm('この予約を削除しますか？')) {
        return;
    }
    
    try {
        showLoading(true);
        const response = await fetch(`api/reservations.php?id=${reservationId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('予約を削除しました', 'success');
            closeModal();
            await loadReservations();
            renderCalendar();
        } else {
            showMessage(result.error || '予約の削除に失敗しました', 'error');
        }
    } catch (error) {
        console.error('予約削除エラー:', error);
        showMessage('予約の削除に失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}

// モーダルを閉じる
function closeModal() {
    const form = document.getElementById('reservation-form');
    const deleteBtn = document.getElementById('delete-btn');
    
    // 編集フラグをクリア
    form.removeAttribute('data-edit-id');
    
    // 削除ボタンを非表示
    if (deleteBtn) {
        deleteBtn.style.display = 'none';
    }
    
    // フォームリセット
    form.reset();
    document.getElementById('modal-title').textContent = '新規予約';
    
    document.getElementById('reservation-modal').style.display = 'none';
}

// 繰り返し予約オプション切り替え
function toggleRecurringOptions() {
    const checkbox = document.getElementById('is-recurring');
    const options = document.getElementById('recurring-options');
    
    if (checkbox.checked) {
        options.style.display = 'block';
    } else {
        options.style.display = 'none';
    }
}

// 予約フォーム送信
async function handleReservationSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const editId = form.getAttribute('data-edit-id');
    const formData = new FormData(form);
    
    const data = {
        title: formData.get('title'),
        description: formData.get('description'),
        date: formData.get('date'),
        start_time: formData.get('start_time'),
        end_time: formData.get('end_time'),
        is_recurring: formData.get('is_recurring') === 'on',
        repeat_type: formData.get('repeat_type'),
        repeat_end_date: formData.get('repeat_end_date')
    };
    
    // 編集の場合はIDを追加
    if (editId) {
        data.id = editId;
    }
    
    try {
        showLoading(true);
        
        const url = 'api/reservations.php';
        const method = editId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            const message = editId ? '予約を更新しました' : '予約を作成しました';
            showMessage(message, 'success');
            closeModal();
            await loadReservations();
            renderCalendar();
        } else {
            const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
            showMessage(result.error || message, 'error');
        }
    } catch (error) {
        console.error('予約処理エラー:', error);
        const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
        showMessage(message, 'error');
    } finally {
        showLoading(false);
    }
}

// ユーティリティ関数
function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function isSameDay(date1, date2) {
    return date1.toDateString() === date2.toDateString();
}

function getMonthStart(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function getMonthEnd(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

function getWeekStart(date) {
    const day = date.getDay();
    const diff = date.getDate() - day;
    return new Date(date.setDate(diff));
}

function getWeekEnd(date) {
    const day = date.getDay();
    const diff = date.getDate() + (6 - day);
    return new Date(date.setDate(diff));
}

function getDayReservations(date) {
    const dateStr = formatDate(date);
    return reservations.filter(res => res.date === dateStr);
}

function showMessage(message, type = 'success') {
    const messageEl = document.getElementById('message');
    if (!messageEl) return;
    
    messageEl.textContent = message;
    messageEl.className = `message ${type}`;
    messageEl.classList.add('show');
    
    setTimeout(() => {
        messageEl.classList.remove('show');
    }, 3000);
}

function clearMessage() {
    const messageEl = document.getElementById('message');
    if (messageEl) {
        messageEl.classList.remove('show');
    }
}

function showLoading(show) {
    const loadingEl = document.getElementById('loading');
    if (loadingEl) {
        loadingEl.style.display = show ? 'flex' : 'none';
    }
}