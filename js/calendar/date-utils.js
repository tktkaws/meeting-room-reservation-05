// 日付ユーティリティ関数
function formatDate(date) {
    // タイムゾーンを考慮したローカル日付フォーマット
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
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

// 平日表示用の開始日取得（その月の最初の平日またはその前の週の月曜日）
function getWeekdayStart(date) {
    const monthStart = new Date(date.getFullYear(), date.getMonth(), 1);
    const dayOfWeek = monthStart.getDay(); // 0=日曜日, 1=月曜日, ..., 6=土曜日
    
    if (dayOfWeek === 0) {
        // 月初が日曜日の場合、翌日（月曜日）から開始
        return new Date(monthStart.getFullYear(), monthStart.getMonth(), 2);
    } else if (dayOfWeek === 1) {
        // 月初が月曜日の場合、そのまま月初から開始
        return new Date(monthStart);
    } else {
        // 月初が火曜日以降の場合、前の週の月曜日から開始
        const daysBack = dayOfWeek - 1;
        const startDate = new Date(monthStart);
        startDate.setDate(startDate.getDate() - daysBack);
        return startDate;
    }
}

// 平日表示用の終了日取得（その月の最後の平日またはその次の週の金曜日）
function getWeekdayEnd(date) {
    const monthEnd = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    const dayOfWeek = monthEnd.getDay(); // 0=日曜日, 1=月曜日, ..., 6=土曜日
    
    if (dayOfWeek === 5) {
        // 月末が金曜日の場合、そのまま月末で終了
        return new Date(monthEnd);
    } else if (dayOfWeek === 6) {
        // 月末が土曜日の場合、前日（金曜日）で終了
        const endDate = new Date(monthEnd);
        endDate.setDate(endDate.getDate() - 1);
        return endDate;
    } else if (dayOfWeek === 0) {
        // 月末が日曜日の場合、前々日（金曜日）で終了
        const endDate = new Date(monthEnd);
        endDate.setDate(endDate.getDate() - 2);
        return endDate;
    } else {
        // 月末が平日の場合、次の金曜日まで延長
        const daysForward = 5 - dayOfWeek;
        const endDate = new Date(monthEnd);
        endDate.setDate(endDate.getDate() + daysForward);
        return endDate;
    }
}

// 週間表示用の開始日取得（その週の月曜日）
function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // 日曜日の場合は前週の月曜日
    return new Date(d.setDate(diff));
}

// 週間表示用の終了日取得（その週の金曜日）
function getWeekEnd(date) {
    const weekStart = getWeekStart(date);
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 4); // 月曜日から4日後が金曜日
    return weekEnd;
}

// 15分単位の時間スロットを生成
function generateTimeSlots() {
    const slots = [];
    const startHour = 9;
    const endHour = 18;
    
    for (let hour = startHour; hour <= endHour; hour++) {
        // 18時の場合は00分のみを追加（18:00で終了）
        const maxMinute = hour === endHour ? 0 : 60;
        for (let minute = 0; minute < maxMinute; minute += 15) {
            const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            slots.push(timeString);
        }
    }
    
    return slots;
}