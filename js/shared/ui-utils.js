// UI共通機能
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

// ビュー状態の保存・復元
const VIEW_STORAGE_KEY = 'meeting-room-current-view';

function saveCurrentView(viewType) {
    try {
        localStorage.setItem(VIEW_STORAGE_KEY, viewType);
    } catch (error) {
        console.warn('ビュー状態の保存に失敗しました:', error);
    }
}

function loadSavedView() {
    try {
        const savedView = localStorage.getItem(VIEW_STORAGE_KEY);
        return savedView || 'month'; // デフォルトは月表示
    } catch (error) {
        console.warn('ビュー状態の読み込みに失敗しました:', error);
        return 'month';
    }
}