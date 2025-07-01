// メインページ専用エントリーポイント

// メインページの初期化
async function initMainPage() {
    // 共通ヘッダーを更新
    await updateCommonHeader();
    
    // 認証チェック
    const authStatus = await checkAuth();
    const isLoggedIn = authStatus.logged_in;
    
    if (isLoggedIn) {
        // currentUserをタイムスタンプ付きで設定
        currentUser = { ...authStatus.user, lastUpdated: Date.now() };
        // console.log('initMainPage: Setting currentUser to:', currentUser);
        
        // セッションストレージから最新のユーザー情報を取得（設定ページで更新された可能性があるため）
        refreshUserInfoFromSession();
        updateUserInfo();
        setupMainPageUserUI();
    } else {
        // 非ログインユーザー向けUI設定
        setupGuestUserUI();
    }
    
    // イベントリスナー設定
    setupEventListeners(isLoggedIn);
    
    // 認証モーダル初期化
    initAuthModal();
    
    // 設定モーダル初期化
    if (isLoggedIn && typeof initConfigPage === 'function') {
        initConfigPage();
    }
    
    // 保存されたビュー状態を復元（ログイン状態に関係なく）
    await restoreSavedView();
    
    // カレンダー初期表示
    await loadReservations();
    renderCalendar();
    
    // サイドバーの表示状態を更新
    updateSidebarVisibility();
}

// ページが表示された時にユーザー情報を更新（設定ページから戻った時など）
window.addEventListener('pageshow', function(event) {
    if (currentUser) {
        // console.log('pageshow event: refreshing user info');
        refreshUserInfoFromSession();
        // サイドバーの表示状態を更新
        updateSidebarVisibility();
    }
});

// メインページ用ログインユーザーUI設定
function setupMainPageUserUI() {
    // 新規予約ボタンを表示
    const newReservationBtn = document.getElementById('new-reservation-btn');
    if (newReservationBtn) {
        newReservationBtn.style.display = 'inline-block';
    }
    
    // ログインボタンを非表示
    const loginBtn = document.getElementById('login-btn');
    if (loginBtn) {
        loginBtn.style.display = 'none';
    }
}

// 非ログインユーザー向けUI設定
function setupGuestUserUI() {
    // ログインボタンのみ表示
    const loginBtn = document.getElementById('login-btn');
    if (loginBtn) {
        loginBtn.style.display = 'inline-block';
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
    
    // ログインボタン（非ログイン時のみ表示）
    document.getElementById('login-btn')?.addEventListener('click', () => openAuthModal('login'));
    
    // ナビゲーションボタン（非ログインでも利用可能）
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    
    if (prevBtn) {
        // 既存のイベントリスナーを削除してから新しく設定
        prevBtn.replaceWith(prevBtn.cloneNode(true));
        const newPrevBtn = document.getElementById('prev-btn');
        newPrevBtn.addEventListener('click', () => {
            navigateDate(-1);
        });
    }
    
    if (nextBtn) {
        // 既存のイベントリスナーを削除してから新しく設定
        nextBtn.replaceWith(nextBtn.cloneNode(true));
        const newNextBtn = document.getElementById('next-btn');
        newNextBtn.addEventListener('click', () => {
            navigateDate(1);
        });
    }
    
    document.getElementById('today-btn')?.addEventListener('click', goToToday);
    
    // ビュー切り替え（非ログインでも利用可能）
    document.getElementById('month-view')?.addEventListener('click', () => switchView('month'));
    document.getElementById('week-view')?.addEventListener('click', () => switchView('week'));
    document.getElementById('list-view')?.addEventListener('click', async () => await switchView('list'));
    document.getElementById('day-view')?.addEventListener('click', () => switchView('day'));
    
    // 詳細表示モーダル（非ログインでも利用可能、編集機能は制限）
    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    
    document.getElementById('reservation-detail-modal')?.addEventListener('mousedown', function(e) {
        if (e.target === this) {
            const startX = e.clientX;
            const startY = e.clientY;
            
            const handleMouseUp = (upEvent) => {
                const endX = upEvent.clientX;
                const endY = upEvent.clientY;
                const distance = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
                
                // ドラッグ距離が5px以下の場合のみクリックとみなす
                if (distance <= 5 && upEvent.target === this) {
                    closeDetailModal();
                }
                
                document.removeEventListener('mouseup', handleMouseUp);
            };
            
            document.addEventListener('mouseup', handleMouseUp);
        }
    });
    
    
    // ログインユーザーのみの機能
    if (isLoggedIn) {
        // ログアウト・設定ボタン
        document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
        document.getElementById('config-btn')?.addEventListener('click', openConfigModal);
        
        // 新規予約ボタン
        document.getElementById('new-reservation-btn')?.addEventListener('click', () => {
            // 今日の日付をYYYY-MM-DD形式で取得
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayStr = `${year}-${month}-${day}`;
            
            openNewReservationModal(todayStr);
        });
        
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
        
        // 繰り返し予約のプレビュー更新
        document.getElementById('reservation-date')?.addEventListener('change', function() {
            if (document.getElementById('recurring-yes')?.checked) {
                setupRecurringEndDateDefault();
            }
        });
        document.getElementById('repeat-end-date')?.addEventListener('change', updateRecurringPreview);
        document.getElementById('repeat-type')?.addEventListener('change', updateRecurringPreview);
        
        
        // モーダル外クリックで閉じる（ドラッグ操作を考慮）
        document.getElementById('reservation-modal')?.addEventListener('mousedown', function(e) {
            if (e.target === this) {
                const startX = e.clientX;
                const startY = e.clientY;
                
                const handleMouseUp = (upEvent) => {
                    const endX = upEvent.clientX;
                    const endY = upEvent.clientY;
                    const distance = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
                    
                    // ドラッグ距離が5px以下の場合のみクリックとみなす
                    if (distance <= 5 && upEvent.target === this) {
                        closeModal();
                    }
                    
                    document.removeEventListener('mouseup', handleMouseUp);
                };
                
                document.addEventListener('mouseup', handleMouseUp);
            }
        });
        
        document.getElementById('group-edit-modal')?.addEventListener('mousedown', function(e) {
            if (e.target === this) {
                const startX = e.clientX;
                const startY = e.clientY;
                
                const handleMouseUp = (upEvent) => {
                    const endX = upEvent.clientX;
                    const endY = upEvent.clientY;
                    const distance = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
                    
                    // ドラッグ距離が5px以下の場合のみクリックとみなす
                    if (distance <= 5 && upEvent.target === this) {
                        closeGroupEditModal();
                    }
                    
                    document.removeEventListener('mouseup', handleMouseUp);
                };
                
                document.addEventListener('mouseup', handleMouseUp);
            }
        });
    }
    
    // 画面リサイズイベントリスナー（週間ビューの予約配置更新）
    window.addEventListener('resize', handleResize);
    
    // 設定モーダル関連イベントリスナー
    setupConfigModalListeners();
    
    // サイドバーのイベントリスナー
    setupSidebarListeners();
    
    // サイドバーオーバーレイのイベントリスナー
    document.getElementById('sidebar-overlay')?.addEventListener('click', () => {
        if (commonHeader) {
            commonHeader.closeSidebar();
        }
    });
}

// 設定モーダルのイベントリスナー設定
function setupConfigModalListeners() {
    // 設定モーダル閉じるボタン
    document.getElementById('close-config-modal')?.addEventListener('click', closeConfigModal);
    
    // 設定モーダル外クリックで閉じる
    document.getElementById('config-modal')?.addEventListener('mousedown', function(e) {
        if (e.target === this) {
            const startX = e.clientX;
            const startY = e.clientY;
            
            const handleMouseUp = (upEvent) => {
                const endX = upEvent.clientX;
                const endY = upEvent.clientY;
                const distance = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
                
                // ドラッグ距離が5px以下の場合のみクリックとみなす
                if (distance <= 5 && upEvent.target === this) {
                    closeConfigModal();
                }
                
                document.removeEventListener('mouseup', handleMouseUp);
            };
            
            document.addEventListener('mouseup', handleMouseUp);
        }
    });
}

// サイドバーのイベントリスナー設定
function setupSidebarListeners() {
    // カレンダーボタン
    document.getElementById('sidebar-calendar-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-calendar-btn');
        // カレンダーページに移動（既にメインページ）
    });
    
    // 予約一覧ボタン
    document.getElementById('sidebar-reservations-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-reservations-btn');
        // カレンダーページ（index.html）に移動
        window.location.href = 'index.html';
    });
    
    // 部署管理ボタン
    document.getElementById('sidebar-department-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-department-btn');
        window.location.href = 'admin/department_management.html';
    });
    
    // メール設定ボタン
    document.getElementById('sidebar-email-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-email-btn');
        window.location.href = 'admin/email_notification_admin.html';
    });
    
    // データ出力ボタン
    document.getElementById('sidebar-export-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-export-btn');
        window.location.href = 'admin/csv_export.html';
    });
    
    // データ取込ボタン
    document.getElementById('sidebar-import-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-import-btn');
        window.location.href = 'admin/csv_import.html';
    });
    
    // 設定ボタン
    document.getElementById('sidebar-config-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-config-btn');
        openConfigModal();
    });
    
    // ログインボタン
    document.getElementById('sidebar-login-btn')?.addEventListener('click', () => {
        setActiveSidebarBtn('sidebar-login-btn');
        if (typeof openAuthModal === 'function') {
            openAuthModal('login');
        } else if (commonHeader && commonHeader.showAuthModal) {
            commonHeader.showAuthModal();
        }
    });
    
    // ログアウトボタン
    document.getElementById('sidebar-logout-btn')?.addEventListener('click', () => {
        if (commonHeader) {
            commonHeader.handleLogout();
        }
    });
}

// サイドバーのアクティブボタンを設定
function setActiveSidebarBtn(btnId) {
    // 全てのサイドバーボタンからactiveクラスを削除
    document.querySelectorAll('.sidebar-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 指定されたボタンにactiveクラスを追加
    document.getElementById(btnId)?.classList.add('active');
}

// サイドバーの表示状態を更新（認証状態に応じて）
function updateSidebarVisibility() {
    const adminSection = document.getElementById('admin-section');
    const loginBtn = document.getElementById('sidebar-login-btn');
    const logoutBtn = document.getElementById('sidebar-logout-btn');
    const userSection = document.getElementById('sidebar-user-section');
    const userInfo = document.getElementById('sidebar-user-info');
    const configBtn = document.getElementById('sidebar-config-btn');
    const reservationsBtn = document.getElementById('sidebar-reservations-btn');
    
    if (currentUser) {
        // ログイン済み
        loginBtn.style.display = 'none';
        logoutBtn.style.display = 'flex';
        userSection.style.display = 'block';
        
        // ページに応じてナビゲーションボタンを表示
        const isIndexPage = window.location.pathname.endsWith('index.html') || 
                           window.location.pathname === '/' || 
                           window.location.pathname.endsWith('/');
        const isConfigPage = window.location.pathname.endsWith('config.html');
        
        if (isIndexPage && configBtn) {
            configBtn.style.display = 'flex';
        }
        if (isConfigPage && reservationsBtn) {
            reservationsBtn.style.display = 'flex';
        }
        
        // ユーザー情報を表示
        if (userInfo && currentUser) {
            // console.log('updateSidebarVisibility: Setting sidebar user info to:', currentUser.name);
            userInfo.textContent = currentUser.name || 'ユーザー名不明';
        }
        
        // 部署情報を表示
        updateSidebarDepartmentInfo();
        
        // 管理者の場合は管理セクションを表示
        if (currentUser.role === 'admin') {
            adminSection.style.display = 'block';
        } else {
            adminSection.style.display = 'none';
        }
        
        // テーマカラー設定を読み込み
        loadSidebarThemeColors();
        
        // カレンダーボタンをアクティブに設定
        setActiveSidebarBtn('sidebar-calendar-btn');
    } else {
        // 未ログイン
        loginBtn.style.display = 'flex';
        logoutBtn.style.display = 'none';
        adminSection.style.display = 'none';
        userSection.style.display = 'none';
        if (configBtn) configBtn.style.display = 'none';
        if (reservationsBtn) reservationsBtn.style.display = 'none';
    }
}

// サイドバーの部署情報を更新
async function updateSidebarDepartmentInfo() {
    if (!currentUser) return;
    
    const departmentNameElement = document.getElementById('sidebar-department-name');
    if (!departmentNameElement) return;
    
    try {
        const deptResponse = await fetch('api/departments.php');
        const deptResult = await deptResponse.json();
        
        if (deptResult.success && currentUser.department) {
            const dept = deptResult.departments.find(d => d.id == currentUser.department);
            if (dept) {
                departmentNameElement.textContent = dept.name;
                return;
            }
        }
        
        departmentNameElement.textContent = '部署未設定';
    } catch (error) {
        console.error('部署情報の取得に失敗:', error);
        departmentNameElement.textContent = '部署未設定';
    }
}

// サイドバーのテーマカラー設定を読み込み
async function loadSidebarThemeColors() {
    if (!currentUser) return;
    
    const isIndexPage = window.location.pathname.endsWith('index.html') || 
                       window.location.pathname === '/' || 
                       window.location.pathname.endsWith('/');
    
    if (!isIndexPage) return;
    
    try {
        const deptResponse = await fetch('api/departments.php');
        const deptResult = await deptResponse.json();
        
        if (!deptResult.success) return;
        
        const themeResponse = await fetch('api/theme_colors.php');
        const themeResult = await themeResponse.json();
        
        const themeControls = document.getElementById('sidebar-theme-color-controls');
        if (!themeControls) return;
        
        // 部署ごとのデフォルトカラー定義（APIと同じ定義）
        const defaultColors = {
            "1": "#4299E1", // 青
            "2": "#48BB78", // 緑
            "3": "#ED8936", // オレンジ
            "4": "#9F7AEA", // 紫
            "5": "#38B2AC"  // ティール
        };
        
        // カラーパレットを循環使用するための配列
        const colorPalette = ["#4299E1", "#48BB78", "#ED8936", "#9F7AEA", "#38B2AC", "#F56565", "#38A169", "#D69E2E"];
        
        let html = '<div class="theme-color-header">テーマカラー設定</div>';
        deptResult.departments.forEach((dept, index) => {
            // まずdefaultColorsから取得、なければカラーパレットから循環取得
            const defaultColor = defaultColors[dept.id] || colorPalette[index % colorPalette.length];
            const color = themeResult.colors?.[dept.id] || defaultColor;
            html += `
                <div class="theme-color-item">
                    <input type="color" 
                           class="theme-color-picker" 
                           value="${color}" 
                           data-dept-id="${dept.id}"
                           data-original-color="${color}"
                           data-default-color="${defaultColor}"
                           onchange="handleSidebarThemeColorChange(${dept.id}, this.value)">
                    <span class="theme-color-label">${dept.name}</span>
                </div>
            `;
        });
        
        html += `
            <div class="theme-color-actions">
                <button type="button" class="theme-color-btn theme-color-btn-reset" onclick="resetThemeColors()">
                    <img src="images/refresh.svg" alt="" class="material-icon">
                    デフォルトに戻す
                </button>
                <button type="button" class="theme-color-btn theme-color-btn-save" onclick="saveAllThemeColors()">
                    <img src="images/save.svg" alt="" class="material-icon">
                    設定を保存
                </button>
            </div>
        `;
        
        themeControls.innerHTML = html;
        themeControls.style.display = 'block';
        
    } catch (error) {
        console.error('サイドバーテーマカラーの読み込みに失敗:', error);
    }
}

// デフォルトテーマカラーに戻す（表示のみ）
async function resetThemeColors() {
    try {
        // departmentsテーブルからデフォルトカラーを取得
        const response = await fetch('api/departments.php');
        const data = await response.json();
        
        if (data.success) {
            const departments = data.departments;
            const colorPickers = document.querySelectorAll('.theme-color-picker');
            
            colorPickers.forEach(picker => {
                const deptId = picker.getAttribute('data-dept-id');
                if (deptId) {
                    // 該当する部署のデフォルトカラーを取得
                    const dept = departments.find(d => d.id == deptId);
                    const defaultColor = dept ? (dept.color || '#718096') : '#718096';
                    picker.value = defaultColor;
                } else {
                    picker.value = '#718096';
                }
            });
            
            // console.log('テーマカラーをデパートメントテーブルのデフォルト値にリセットしました（表示のみ）');
        } else {
            console.error('部署情報の取得に失敗:', data.message);
            // フォールバック：固定値でリセット
            const colorPickers = document.querySelectorAll('.theme-color-picker');
            colorPickers.forEach(picker => {
                const defaultColor = picker.getAttribute('data-default-color') || '#718096';
                picker.value = defaultColor;
            });
        }
    } catch (error) {
        console.error('デフォルトカラー取得エラー:', error);
        // フォールバック：固定値でリセット
        const colorPickers = document.querySelectorAll('.theme-color-picker');
        colorPickers.forEach(picker => {
            const defaultColor = picker.getAttribute('data-default-color') || '#718096';
            picker.value = defaultColor;
        });
    }
}

// 全てのテーマカラー設定を保存
async function saveAllThemeColors() {
    if (!currentUser || !currentUser.id) {
        console.error('ユーザー情報が取得できていません');
        showToast('ユーザー情報が取得できていません', 'error');
        return;
    }

    try {
        const colorPickers = document.querySelectorAll('.theme-color-picker');
        const colors = {};
        colorPickers.forEach(picker => {
            const deptId = picker.getAttribute('data-dept-id');
            const color = picker.value;
            if (deptId && color) {
                colors[deptId] = color;
            }
        });
        if (Object.keys(colors).length === 0) {
            showToast('保存するカラー設定がありません', 'warning');
            return;
        }
        // PUTでまとめて送信
        const response = await fetch('api/theme_colors.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ colors })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showToast('テーマカラー設定を保存しました！', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('カラー設定の保存に失敗しました。', 'error');
        }
    } catch (error) {
        console.error('テーマカラー保存エラー:', error);
        showToast('カラー設定の保存に失敗しました。', 'error');
    }
}

// トースト表示（画面右下）
function showToast(message, type = 'info') {
    // 既存のトーストを削除
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // 新しいトーストを作成
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    // bodyに追加
    document.body.appendChild(toast);
    
    // アニメーション開始
    setTimeout(() => {
        toast.classList.add('toast-show');
    }, 100);
    
    // 自動削除
    setTimeout(() => {
        toast.classList.remove('toast-show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// サイドバーテーマカラー変更ハンドラー（個別変更時は即座に反映しない）
function handleSidebarThemeColorChange(deptId, color) {
}

// 設定モーダルを開く
function openConfigModal() {
    // ログイン状態をチェック
    if (!currentUser) {
        openAuthModal('login');
        return;
    }
    
    const modal = document.getElementById('config-modal');
    if (modal) {
        modal.style.display = 'flex';
        
        // ユーザー情報をモーダルに読み込み
        if (typeof loadUserData === 'function') {
            loadUserData();
        }
        // 部署データを読み込み
        if (typeof loadDepartments === 'function') {
            loadDepartments();
        }
    }
}

// 設定モーダルを閉じる
function closeConfigModal() {
    const modal = document.getElementById('config-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// リサイズハンドラー（週間ビュー用）
function handleResize() {
    // 週間ビューの予約配置を更新する処理
    if (typeof updateWeekViewReservations === 'function') {
        updateWeekViewReservations();
    }
}

// ページ初期化
document.addEventListener('DOMContentLoaded', function() {
    initMainPage();
});