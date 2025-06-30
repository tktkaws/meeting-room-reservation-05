// 共通ヘッダー機能
class CommonHeader {
    constructor(options = {}) {
        this.currentUser = null;
        this.options = options;
        this.init();
    }

    init() {
        this.loadHeader();
        this.setupEventListeners();
    }

    loadHeader() {
        // ヘッダーはもう使用しないため空にする
        const headerElement = document.querySelector('.header');
        if (headerElement) {
            headerElement.style.display = 'none';
        }
    }

    setupEventListeners() {
        // モバイルハンバーガーボタン
        document.getElementById('mobile-hamburger-btn')?.addEventListener('click', () => {
            this.toggleSidebar();
        });
        
        // サイドバー閉じるボタン
        document.getElementById('hamburger-close-btn')?.addEventListener('click', () => {
            this.closeSidebar();
        });

        // 設定ボタン
        document.getElementById('config-btn')?.addEventListener('click', () => {
            window.location.href = 'config.html';
        });

        // ログアウトボタン
        document.getElementById('logout-btn')?.addEventListener('click', () => {
            this.handleLogout();
        });
    }

    showAuthModal() {
        // 認証モーダルを表示（既存の認証モーダル機能を使用）
        if (typeof showAuthModal === 'function') {
            showAuthModal();
        } else {
            // window.location.href = 'auth.html';
        }
    }

    async handleLogout() {
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

    async updateHeader() {
        try {
            const authStatus = await this.checkAuth();
            const isLoggedIn = authStatus.logged_in;
            
            if (isLoggedIn) {
                this.currentUser = { ...authStatus.user, lastUpdated: Date.now() };
                // console.log('CommonHeader: updateHeader setting currentUser to:', this.currentUser);
                
                // グローバルcurrentUserも更新
                if (typeof currentUser !== 'undefined') {
                    currentUser = this.currentUser;
                }
                
                this.refreshUserInfoFromSession();
                this.updateUserInfo();
                this.setupLoggedInUserUI();
            } else {
                this.setupGuestUserUI();
            }
        } catch (error) {
            console.error('ヘッダー更新エラー:', error);
            this.setupGuestUserUI();
        }
    }

    async checkAuth() {
        try {
            const response = await fetch('api/auth.php');
            const result = await response.json();
            
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

    refreshUserInfoFromSession() {
        if (typeof Storage !== 'undefined') {
            try {
                const authData = JSON.parse(sessionStorage.getItem('authData') || '{}');
                if (authData.user && authData.timestamp) {
                    // セッションストレージのデータが現在のthis.currentUserより新しい場合のみ更新
                    const sessionTimestamp = authData.timestamp || 0;
                    const currentTimestamp = this.currentUser?.lastUpdated || 0;
                    
                    if (sessionTimestamp > currentTimestamp) {
                        console.log('CommonHeader: セッションストレージから新しいユーザー情報を更新:', authData.user);
                        this.currentUser = { ...authData.user, lastUpdated: sessionTimestamp };
                        
                        // グローバルcurrentUserも更新
                        if (typeof currentUser !== 'undefined') {
                            currentUser = this.currentUser;
                        }
                    }
                }
            } catch (error) {
                console.warn('セッションストレージからの更新に失敗しました:', error);
            }
        }
    }

    async updateUserInfo() {
        if (!this.currentUser) return;

        try {
            // 部署データを取得
            const deptResponse = await fetch('api/departments.php');
            const deptData = await deptResponse.json();
            
            let departmentName = '部署未設定';
            if (deptData.success && this.currentUser.department) {
                const dept = deptData.departments.find(d => d.id == this.currentUser.department);
                if (dept) {
                    departmentName = dept.name;
                }
            }
            
            const userInfo = document.getElementById('user-info');
            if (userInfo) {
                userInfo.textContent = `${this.currentUser.name} (${departmentName})`;
            }
        } catch (error) {
            console.error('部署情報の取得に失敗しました:', error);
            const userInfo = document.getElementById('user-info');
            if (userInfo && this.currentUser) {
                userInfo.textContent = `${this.currentUser.name} (部署未設定)`;
            }
        }
    }

    setupLoggedInUserUI() {
        // ユーザー情報表示を表示
        const userInfo = document.getElementById('user-info');
        if (userInfo) {
            userInfo.style.display = 'inline-block';
        }
        
        // 予約一覧ボタンを表示（現在のページがindex.htmlでない場合）
        const reservationsBtn = document.getElementById('reservations-btn');
        if (reservationsBtn) {
            const isIndexPage = window.location.pathname.endsWith('index.html') || 
                               window.location.pathname === '/' || 
                               window.location.pathname.endsWith('/');
            reservationsBtn.style.display = isIndexPage ? 'none' : 'inline-block';
        }
        
        
        // 設定ボタンを表示（現在のページがconfig.htmlでない場合）
        const configBtn = document.getElementById('config-btn');
        if (configBtn) {
            const isConfigPage = window.location.pathname.endsWith('config.html');
            configBtn.style.display = isConfigPage ? 'none' : 'inline-block';
        }
        
        // ログアウトボタンを表示
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.style.display = 'inline-block';
        }
        
        // ログインボタンを非表示
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) {
            loginBtn.style.display = 'none';
        }

        // テーマカラー設定を表示（index.htmlでログイン中の場合）
        this.loadThemeColorControls();
        
        // サイドバーの表示状態を更新
        if (typeof updateSidebarVisibility === 'function') {
            updateSidebarVisibility();
        }
    }

    setupGuestUserUI() {
        // ユーザー情報表示を非表示
        const userInfo = document.getElementById('user-info');
        if (userInfo) {
            userInfo.style.display = 'none';
        }
        
        // 予約一覧ボタンを非表示
        const reservationsBtn = document.getElementById('reservations-btn');
        if (reservationsBtn) {
            reservationsBtn.style.display = 'none';
        }
        
        
        // 設定ボタンを非表示
        const configBtn = document.getElementById('config-btn');
        if (configBtn) {
            configBtn.style.display = 'none';
        }
        
        // ログアウトボタンを非表示
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.style.display = 'none';
        }
        
        // ログインボタンを表示
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) {
            loginBtn.style.display = 'inline-block';
        }

        // テーマカラー設定を非表示
        const themeColorControls = document.getElementById('theme-color-controls');
        if (themeColorControls) {
            themeColorControls.style.display = 'none';
        }
        
        // サイドバーの表示状態を更新
        if (typeof updateSidebarVisibility === 'function') {
            updateSidebarVisibility();
        }
    }

    async loadThemeColorControls() {
        
        if (!this.currentUser) {
            return;
        }

        const isIndexPage = window.location.pathname.endsWith('index.html') || 
                           window.location.pathname === '/' || 
                           window.location.pathname.endsWith('/');
        
        
        if (!isIndexPage) {
            return;
        }

        try {
            const deptResponse = await fetch('api/departments.php');
            const deptResult = await deptResponse.json();
            
            if (!deptResult.success) {
                return;
            }

            const themeResponse = await fetch('api/theme_colors.php');
            const themeResult = await themeResponse.json();
            
            const themeControls = document.getElementById('theme-color-controls');
            if (!themeControls) {
                return;
            }

            let controlsHTML = '<div class="theme-color-title">テーマカラー</div>';
            
            deptResult.departments.forEach(dept => {
                const currentColor = themeResult.colors?.[dept.id] || '#007bff';
                controlsHTML += `
                    <div class="theme-color-item">
                        
                        <input type="color" 
                               id="theme-color-${dept.id}" 
                               value="${currentColor}" 
                               data-dept-id="${dept.id}"
                               class="theme-color-picker">
                               <label for="theme-color-${dept.id}">${dept.name}</label>
                    </div>
                `;
            });

            // ボタンを追加
            controlsHTML += `
                <div class="theme-color-actions">
                    <button type="button" id="update-theme-colors-btn" class="btn-secondary bttn-sidebar">
                        保存
                    </button>
                    <button type="button" id="reset-theme-colors-btn" class="btn-secondary bttn-sidebar">
                        デフォルトに戻す
                    </button>
                    
                    
                </div>
            `;

            themeControls.innerHTML = controlsHTML;
            themeControls.style.display = 'block';

            // カラーピッカーのイベントリスナーを設定
            const colorPickers = themeControls.querySelectorAll('.theme-color-picker');
            colorPickers.forEach(picker => {
                picker.addEventListener('change', this.handleThemeColorChange.bind(this));
            });

            // 更新ボタンのイベントリスナーを設定
            const updateBtn = document.getElementById('update-theme-colors-btn');
            if (updateBtn) {
                updateBtn.addEventListener('click', this.handleThemeColorUpdate.bind(this));
            }

            // リセットボタンのイベントリスナーを設定
            const resetBtn = document.getElementById('reset-theme-colors-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', this.handleThemeColorReset.bind(this));
            }

        } catch (error) {
            console.error('テーマカラー設定の読み込みに失敗しました:', error);
        }
    }

    async handleThemeColorChange(event) {
        const deptId = event.target.getAttribute('data-dept-id');
        const color = event.target.value;

        try {
            const formData = new FormData();
            formData.append('department_id', deptId);
            formData.append('color', color);

            const response = await fetch('api/theme_colors.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.status === 'success') {
                // カレンダーの再描画をトリガー
                if (typeof window.calendar !== 'undefined' && window.calendar.render) {
                    window.calendar.render();
                }
            } else {
                console.error('テーマカラーの保存に失敗しました:', result.message);
            }
        } catch (error) {
            console.error('テーマカラーの保存に失敗しました:', error);
        }
    }

    async handleThemeColorUpdate() {
        try {
            // 全部署のカラー設定を取得
            const colorPickers = document.querySelectorAll('.theme-color-picker');
            const colors = {};
            
            colorPickers.forEach(picker => {
                const deptId = picker.getAttribute('data-dept-id');
                const color = picker.value;
                colors[deptId] = color;
            });

            // 一括でテーマカラーを更新
            const response = await fetch('api/theme_colors.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ colors: colors })
            });

            const result = await response.json();
            if (result.status === 'success') {
                // 成功メッセージを表示
                this.showMessage('テーマカラーを更新しました。画面を再読み込みします。');
                
                // 1秒後に画面をリロード
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('テーマカラーの一括更新に失敗しました:', result.message);
                this.showMessage('テーマカラーの更新に失敗しました。', 'error');
            }
        } catch (error) {
            console.error('テーマカラーの一括更新に失敗しました:', error);
            this.showMessage('テーマカラーの更新に失敗しました。', 'error');
        }
    }

    async handleThemeColorReset() {
        if (!confirm('テーマカラーをデフォルトに戻しますか？')) {
            return;
        }

        try {
            // デフォルトカラー設定
            const defaultColors = {
                "1": "#4299E1",
                "2": "#48BB78",
                "3": "#ED8936",
                "4": "#9F7AEA",
                "5": "#38B2AC"
            };

            // デフォルトカラーで更新
            const response = await fetch('api/theme_colors.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ colors: defaultColors })
            });

            const result = await response.json();
            if (result.status === 'success') {
                // 成功メッセージを表示
                this.showMessage('テーマカラーをデフォルトに戻しました。画面を再読み込みします。');
                
                // 1秒後に画面をリロード
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('テーマカラーのリセットに失敗しました:', result.message);
                this.showMessage('テーマカラーのリセットに失敗しました。', 'error');
            }
        } catch (error) {
            console.error('テーマカラーのリセットに失敗しました:', error);
            this.showMessage('テーマカラーのリセットに失敗しました。', 'error');
        }
    }

    showMessage(text, type = 'success') {
        const messageElement = document.getElementById('message');
        if (messageElement) {
            messageElement.textContent = text;
            messageElement.className = `message ${type}`;
            messageElement.style.display = 'block';
            
            setTimeout(() => {
                messageElement.style.display = 'none';
            }, 3000);
        }
    }

    // サイドバーの開閉制御
    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (sidebar && overlay) {
            const isOpen = sidebar.classList.contains('sidebar-open');
            
            if (isOpen) {
                this.closeSidebar();
            } else {
                this.openSidebar();
            }
        }
    }

    openSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (sidebar && overlay) {
            sidebar.classList.add('sidebar-open');
            overlay.classList.add('sidebar-overlay-active');
            document.body.style.overflow = 'hidden'; // スクロールを無効化
        }
    }

    closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (sidebar && overlay) {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('sidebar-overlay-active');
            document.body.style.overflow = ''; // スクロールを有効化
        }
    }
}

// グローバルインスタンス
let commonHeader = null;

// ヘッダー初期化関数
function initCommonHeader() {
    commonHeader = new CommonHeader();
    return commonHeader;
}

// ヘッダー更新関数
async function updateCommonHeader() {
    if (commonHeader) {
        await commonHeader.updateHeader();
    }
}