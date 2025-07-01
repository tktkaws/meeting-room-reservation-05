-- データベース完全再構築スクリプト（ステップ分割版）

-- ========== STEP1: テーブル削除 ==========
DROP TABLE IF EXISTS user_department_colors
-- ========== END_STEP1 ==========

-- ========== STEP2: テーブル削除 ==========
DROP TABLE IF EXISTS departments
-- ========== END_STEP2 ==========

-- ========== STEP3: departmentsテーブル作成 ==========
CREATE TABLE departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    display_order INTEGER DEFAULT 0,
    color TEXT DEFAULT '#718096',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
-- ========== END_STEP3 ==========

-- ========== STEP4: user_department_colorsテーブル作成 ==========
CREATE TABLE user_department_colors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    department_id INTEGER NOT NULL,
    color TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE(user_id, department_id)
)
-- ========== END_STEP4 ==========

-- ========== STEP5: インデックス作成1 ==========
CREATE INDEX IF NOT EXISTS idx_user_department_colors_user ON user_department_colors(user_id)
-- ========== END_STEP5 ==========

-- ========== STEP6: インデックス作成2 ==========
CREATE INDEX IF NOT EXISTS idx_user_department_colors_dept ON user_department_colors(department_id)
-- ========== END_STEP6 ==========

-- ========== STEP7: 基本部署データ挿入1 ==========
INSERT INTO departments (name, display_order, color) VALUES ('取締役', 1, '#4299E1')
-- ========== END_STEP7 ==========

-- ========== STEP8: 基本部署データ挿入2 ==========
INSERT INTO departments (name, display_order, color) VALUES ('総務管理部', 2, '#48BB78')
-- ========== END_STEP8 ==========

-- ========== STEP9: 基本部署データ挿入3 ==========
INSERT INTO departments (name, display_order, color) VALUES ('事業開発部', 3, '#ED8936')
-- ========== END_STEP9 ==========

-- ========== STEP10: 基本部署データ挿入4 ==========
INSERT INTO departments (name, display_order, color) VALUES ('制作', 4, '#9F7AEA')
-- ========== END_STEP10 ==========

-- ========== STEP11: 基本部署データ挿入5 ==========
INSERT INTO departments (name, display_order, color) VALUES ('営業', 5, '#38B2AC')
-- ========== END_STEP11 ==========