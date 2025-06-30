// 部署管理JavaScript
class DepartmentManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadDepartments();
    }

    bindEvents() {
        // 追加フォーム
        document.getElementById('add-department-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.addDepartment();
        });
    }

    async loadDepartments() {
        this.showLoading(true);
        try {
            const response = await fetch('../api/departments.php');
            const data = await response.json();
            
            if (data.success) {
                this.displayDepartments(data.departments);
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError('部署の読み込みに失敗しました: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }

    displayDepartments(departments) {
        const container = document.getElementById('departments-list');
        
        if (departments.length === 0) {
            container.innerHTML = '<p>部署が登録されていません。</p>';
            return;
        }

        const html = departments.map(dept => `
            <div class="department-item" id="dept-${dept.id}">
                <form class="department-form" onsubmit="departmentManager.updateDepartment(event, ${dept.id})">
                    <div class="form-row">
                        <label>部署名:</label>
                        <input type="text" value="${this.escapeHtml(dept.name)}" name="name" required>
                    </div>
                    <div class="form-row">
                        <label>表示順:</label>
                        <input type="number" value="${dept.display_order}" name="display_order" min="0">
                    </div>
                    <div class="form-row">
                        <button type="submit" class="btn btn-small btn-primary">更新</button>
                        <button type="button" class="btn btn-small btn-danger" onclick="departmentManager.deleteDepartment(${dept.id}, '${this.escapeHtml(dept.name)}')">
                            削除
                        </button>
                    </div>
                </form>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    async addDepartment() {
        const name = document.getElementById('department-name').value.trim();
        const displayOrder = document.getElementById('display-order').value;

        if (!name) {
            this.showError('部署名を入力してください');
            return;
        }

        this.showLoading(true);
        try {
            const response = await fetch('../api/departments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    name: name,
                    display_order: displayOrder ? parseInt(displayOrder) : null
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                document.getElementById('add-department-form').reset();
                this.loadDepartments();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError('部署の追加に失敗しました: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }

    async updateDepartment(event, id) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const name = formData.get('name').trim();
        const displayOrder = formData.get('display_order');

        if (!name) {
            this.showError('部署名を入力してください');
            return;
        }

        this.showLoading(true);
        try {
            const response = await fetch('../api/departments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update',
                    id: id,
                    name: name,
                    display_order: displayOrder ? parseInt(displayOrder) : null
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadDepartments();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError('部署の更新に失敗しました: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }


    async deleteDepartment(id, name) {
        if (!confirm(`部署「${name}」を削除しますか？\n\n注意: この部署を使用しているユーザーがいる場合は削除できません。`)) {
            return;
        }

        this.showLoading(true);
        try {
            const response = await fetch('../api/departments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    id: id
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadDepartments();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError('部署の削除に失敗しました: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }


    showLoading(show) {
        document.getElementById('loading').style.display = show ? 'block' : 'none';
    }

    showError(message) {
        const errorEl = document.getElementById('error-message');
        errorEl.textContent = message;
        errorEl.style.display = 'block';
        setTimeout(() => {
            errorEl.style.display = 'none';
        }, 5000);
    }

    showSuccess(message) {
        const successEl = document.getElementById('success-message');
        successEl.textContent = message;
        successEl.style.display = 'block';
        setTimeout(() => {
            successEl.style.display = 'none';
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// 初期化
const departmentManager = new DepartmentManager();