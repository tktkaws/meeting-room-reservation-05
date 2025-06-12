// 日付ユーティリティ関数
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

// 平日表示用の開始日取得（その月の最初の平日またはその前の週の月曜日）
function getWeekdayStart(date) {
    const monthStart = new Date(date.getFullYear(), date.getMonth(), 1);
    const dayOfWeek = monthStart.getDay(); // 0=日曜日, 1=月曜日, ..., 6=土曜日
    
    if (dayOfWeek === 0) {
        // 月初が日曜日の場合、翌日（月曜日）から開始
        return new Date(monthStart.getFullYear(), monthStart.getMonth(), 2);
    } else if (dayOfWeek === 1) {
        // 月初が月曜日の場合、そのまま月初から開始
        return monthStart;
    } else {
        // 月初が火曜日以降の場合、前の週の月曜日から開始
        const daysBack = dayOfWeek - 1;
        return new Date(monthStart.getFullYear(), monthStart.getMonth(), monthStart.getDate() - daysBack);
    }
}

// 平日表示用の終了日取得（その月の最後の平日またはその次の週の金曜日）
function getWeekdayEnd(date) {
    const monthEnd = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    const dayOfWeek = monthEnd.getDay(); // 0=日曜日, 1=月曜日, ..., 6=土曜日
    
    if (dayOfWeek === 5) {
        // 月末が金曜日の場合、そのまま月末で終了
        return monthEnd;
    } else if (dayOfWeek === 6) {
        // 月末が土曜日の場合、前日（金曜日）で終了
        return new Date(monthEnd.getFullYear(), monthEnd.getMonth(), monthEnd.getDate() - 1);
    } else if (dayOfWeek === 0) {
        // 月末が日曜日の場合、前々日（金曜日）で終了
        return new Date(monthEnd.getFullYear(), monthEnd.getMonth(), monthEnd.getDate() - 2);
    } else {
        // 月末が平日の場合、次の金曜日まで延長
        const daysForward = 5 - dayOfWeek;
        return new Date(monthEnd.getFullYear(), monthEnd.getMonth(), monthEnd.getDate() + daysForward);
    }
}