// ユーザーテーマカラー管理クラス
class UserThemeColorManager {
    constructor() {
        this.currentDepartmentId = null;
        this.currentColor = '#718096';
        this.init();
    }

    async init() {
        this.bindEvents();
        await this.loadUserInfo();
        if (this.currentDepartmentId) {
            await this.loadCurrentColor();
        }
    }

    bindEvents() {
        // 保存ボタン
        const saveBtn = document.getElementById('save-theme-color-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveColor());
        }

        // リセットボタン
        const resetBtn = document.getElementById('reset-theme-color-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetColor());
        }

        // カラー変更
        const colorInput = document.getElementById('user-theme-color');
        if (colorInput) {
            colorInput.addEventListener('change', (e) => {
                this.currentColor = e.target.value;
            });
        }
    }

    async loadUserInfo() {
        try {
            const response = await fetch('api/check_auth.php');
            const authStatus = await response.json();
            if (authStatus.logged_in) {
                this.currentDepartmentId = authStatus.session_data?.department;
                // テーマカラー設定セクションを表示
                const themeSection = document.getElementById('sidebar-theme-color-controls');
                if (themeSection && this.currentDepartmentId) {
                    themeSection.style.display = 'block';
                }
            }
        } catch (error) {
            console.error('ユーザー情報の取得に失敗:', error);
        }
    }

    async loadCurrentColor() {
        if (!this.currentDepartmentId) {
            console.warn('部署IDが設定されていません');
            return;
        }
        try {
            const response = await fetch('api/theme_colors.php');
            const data = await response.json();
            if (data.status === 'success' && data.colors) {
                this.currentColor = data.colors[String(this.currentDepartmentId)] || '#718096';
                this.updateUI();
            } else {
                console.error('カラー取得エラー:', data.message);
            }
        } catch (error) {
            console.error('カラー取得に失敗:', error);
        }
    }

    updateUI() {
        // カラー入力フィールドを更新
        const colorInput = document.getElementById('user-theme-color');
        if (colorInput) {
            colorInput.value = this.currentColor;
        }
        // 即座にカラーを適用
        this.applyThemeColor();
        // カラーソース情報を更新
        const sourceInfo = document.getElementById('theme-color-source');
        if (sourceInfo) {
            sourceInfo.textContent = '部署のテーマカラーを使用中';
        }
    }

    async saveColor() {
        if (!this.currentDepartmentId) {
            this.showMessage('部署情報が取得できていません', 'error');
            return;
        }
        try {
            const formData = new FormData();
            formData.append('department_id', this.currentDepartmentId);
            formData.append('color', this.currentColor);
            const response = await fetch('api/theme_colors.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'success') {
                this.updateUI();
                this.showMessage('テーマカラーを保存しました', 'success');
                this.applyThemeColor();
                this.refreshCalendarDisplay();
            } else {
                this.showMessage(data.message, 'error');
            }
        } catch (error) {
            console.error('カラー保存に失敗:', error);
            this.showMessage('カラーの保存に失敗しました', 'error');
        }
    }

    refreshCalendarDisplay() {
        if (window.renderCalendar && typeof window.renderCalendar === 'function') {
            setTimeout(() => {
                window.renderCalendar();
                this.applyThemeColor();
            }, 100);
        }
        if (window.loadReservations && typeof window.loadReservations === 'function') {
            setTimeout(() => {
                window.loadReservations().then(() => {
                    this.applyThemeColor();
                });
            }, 200);
        }
    }

    async resetColor() {
        if (!this.currentDepartmentId) {
            this.showMessage('部署情報が取得できていません', 'error');
            return;
        }
        if (!confirm('デフォルトカラーに戻しますか？')) {
            return;
        }
        try {
            // 部署テーブルからデフォルトカラーを取得
            const deptResponse = await fetch('api/departments.php');
            const deptData = await deptResponse.json();
            if (deptData.success) {
                const dept = deptData.departments.find(d => d.id == this.currentDepartmentId);
                this.currentColor = dept ? (dept.color || '#718096') : '#718096';
                this.updateUI();
                this.showMessage('デフォルトカラーに戻しました', 'success');
                this.applyThemeColor();
                this.refreshCalendarDisplay();
            } else {
                this.showMessage('デフォルトカラー取得に失敗', 'error');
            }
        } catch (error) {
            console.error('カラーリセットに失敗:', error);
            this.showMessage('カラーのリセットに失敗しました', 'error');
        }
    }

    applyThemeColor() {
        document.documentElement.style.setProperty('--user-theme-color', this.currentColor);
        this.updateReservationColors();
        window.dispatchEvent(new CustomEvent('themeColorChanged', {
            detail: {
                color: this.currentColor,
                departmentId: this.currentDepartmentId
            }
        }));
    }

    updateReservationColors() {
        if (!this.currentDepartmentId) return;

        // 現在表示されている予約要素のスタイルを更新
        const reservationElements = document.querySelectorAll(`.dept--${this.currentDepartmentId.toString().padStart(2, '0')}`);
        
        reservationElements.forEach(element => {
            // 背景色を設定
            element.style.backgroundColor = this.hexToRgba(this.currentColor, 0.1);
            
            // ボーダー色を設定
            if (element.classList.contains('recurring')) {
                element.style.borderLeftColor = this.currentColor;
            }
            if (element.classList.contains('editable')) {
                element.style.borderColor = this.currentColor;
            }
            
            // 文字色を自動調整
            element.style.color = this.getContrastColor(this.currentColor);
        });

        // 週表示の予約要素も更新
        const weekReservationElements = document.querySelectorAll(`.week-reservation.dept--${this.currentDepartmentId.toString().padStart(2, '0')}`);
        
        weekReservationElements.forEach(element => {
            element.style.backgroundColor = this.currentColor;
            element.style.color = this.getContrastColor(this.currentColor);
        });
    }

    hexToRgba(hex, alpha = 1) {
        const color = hex.replace('#', '');
        const r = parseInt(color.substr(0, 2), 16);
        const g = parseInt(color.substr(2, 2), 16);
        const b = parseInt(color.substr(4, 2), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    getContrastColor(hexColor) {
        const color = hexColor.replace('#', '');
        const r = parseInt(color.substr(0, 2), 16);
        const g = parseInt(color.substr(2, 2), 16);
        const b = parseInt(color.substr(4, 2), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.8 ? '#000000' : '#FFFFFF';
    }

    showMessage(message, type = 'info') {
        // 既存のメッセージ表示システムを使用
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // 実際のプロジェクトのメッセージ表示方法に合わせて調整
        if (window.showNotification) {
            window.showNotification(message, type);
        }
    }
}

// グローバルインスタンス作成
let userThemeColorManager = null;

// DOMContentLoaded後に初期化
document.addEventListener('DOMContentLoaded', () => {
    userThemeColorManager = new UserThemeColorManager();
    
    // グローバルアクセス用
    window.userThemeColorManager = userThemeColorManager;
});