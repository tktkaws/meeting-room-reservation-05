// カレンダー表示機能
let currentView = 'month';
let currentDate = getJapanTime();
let reservations = [];

// 指定日の予約を取得
function getDayReservations(date) {
    const dateStr = formatDate(date);
    return reservations.filter(res => res.date === dateStr);
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
    } else if (currentView === 'list') {
        renderListView(calendarView);
    } else if (currentView === 'day') {
        renderDayView(calendarView);
    }
}

// 月表示（平日のみ）
function renderMonthView(container) {
    const monthStart = getMonthStart(currentDate);
    const monthEnd = getMonthEnd(currentDate);
    const calendarStart = getWeekdayStart(new Date(monthStart));
    const calendarEnd = getWeekdayEnd(new Date(monthEnd));
    
    let html = '<div class="calendar-grid weekdays-only">';
    
    // ヘッダー（平日のみ）
    const weekdays = ['月', '火', '水', '木', '金'];
    weekdays.forEach(day => {
        html += `<div class="calendar-header">${day}</div>`;
    });
    
    // 日付セル（平日のみ）
    let currentDay = new Date(calendarStart);
    while (currentDay <= calendarEnd) {
        // 平日のみ処理（月曜=1, 火曜=2, ..., 金曜=5）
        if (currentDay.getDay() >= 1 && currentDay.getDay() <= 5) {
            // 現在の日付のコピーを作成（参照問題を回避）
            const displayDate = new Date(currentDay);
            const dayReservations = getDayReservations(displayDate);
            const isToday = isSameDay(displayDate, getJapanTime());
            const isCurrentMonth = displayDate.getMonth() === currentDate.getMonth();
            
            html += `
                <div class="calendar-day ${isToday ? 'today' : ''} ${!isCurrentMonth ? 'other-month' : ''}" 
                     data-date="${formatDate(displayDate)}" onclick="selectDate('${formatDate(displayDate)}')">
                    <div class="day-number">${displayDate.getDate()}</div>
                    <div class="reservations">
                        ${dayReservations.map(res => `
                            <div class="reservation-item ${res.group_id ? 'recurring' : ''}" onclick="event.stopPropagation(); showReservationDetail(${res.id})" title="${res.title} (${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)})${res.group_id ? ' - 繰り返し予約' : ''}">
                                <div class="reservation-time">${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)}</div>
                                <div class="reservation-title">${res.title}${res.group_id ? ' ♻' : ''}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        // 日付を1日進める（新しいDateオブジェクトを作成）
        currentDay = new Date(currentDay.getTime() + 24 * 60 * 60 * 1000);
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// 週表示（簡易版）
function renderWeekView(container) {
    container.innerHTML = '<div class="week-view"><p>週表示は今後実装予定です</p></div>';
}

// リスト表示
function renderListView(container) {
    // 全ての今日以降の予約をソート
    const futureReservations = allFutureReservations.sort((a, b) => {
        // 日付と開始時間でソート
        const dateA = new Date(a.start_datetime);
        const dateB = new Date(b.start_datetime);
        return dateA - dateB;
    });
    
    let html = '<div class="list-view">';
    
    if (futureReservations.length === 0) {
        html += '<div class="list-empty">今日以降の予約はありません</div>';
    } else {
        html += '<div class="list-header">';
        html += '<div class="list-header-item">日付</div>';
        html += '<div class="list-header-item">時間</div>';
        html += '<div class="list-header-item">タイトル</div>';
        html += '<div class="list-header-item">予約者</div>';
        html += '<div class="list-header-item">種別</div>';
        html += '</div>';
        
        futureReservations.forEach(res => {
            const date = new Date(res.date);
            const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
            const formattedDate = `${date.getMonth() + 1}/${date.getDate()}(${dayOfWeek})`;
            const startTime = res.start_datetime.split(' ')[1].substring(0,5);
            const endTime = res.end_datetime.split(' ')[1].substring(0,5);
            const timeRange = `${startTime}-${endTime}`;
            const reservationType = res.group_id ? '繰り返し' : '単発';
            
            html += `
                <div class="list-item" onclick="showReservationDetail(${res.id})" data-reservation-id="${res.id}">
                    <div class="list-item-cell list-date">${formattedDate}</div>
                    <div class="list-item-cell list-time">${timeRange}</div>
                    <div class="list-item-cell list-title">${res.title}${res.group_id ? ' ♻' : ''}</div>
                    <div class="list-item-cell list-user">${res.user_name || '不明'}</div>
                    <div class="list-item-cell list-type">
                        <span class="type-badge ${res.group_id ? 'type-recurring' : 'type-single'}">${reservationType}</span>
                    </div>
                </div>
            `;
        });
    }
    
    html += '</div>';
    container.innerHTML = html;
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
    currentDate = getJapanTime();
    loadReservations().then(() => renderCalendar());
}

// ビュー切り替え
async function switchView(view) {
    currentView = view;
    
    // ボタンのアクティブ状態更新
    document.querySelectorAll('.btn-view').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`${view}-view`).classList.add('active');
    
    // リスト表示の場合は今日以降の全予約データを読み込み
    if (view === 'list') {
        await loadAllFutureReservations();
    }
    
    renderCalendar();
}

// 日付選択
function selectDate(dateStr) {
    openNewReservationModal(dateStr);
}