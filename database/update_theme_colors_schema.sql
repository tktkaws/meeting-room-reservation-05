-- テーマカラー機能のためのデータベーススキーマ更新

-- STEP1: 部署テーブルにカラーカラムを追加
ALTER TABLE departments ADD COLUMN color TEXT DEFAULT '#718096'

-- STEP2: ユーザーと部署のカラーリレーションテーブルを作成
CREATE TABLE IF NOT EXISTS user_department_colors (
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

-- STEP3: ユーザーカラーテーブルのインデックス作成
CREATE INDEX IF NOT EXISTS idx_user_department_colors_user ON user_department_colors(user_id)

-- STEP4: 部署カラーテーブルのインデックス作成
CREATE INDEX IF NOT EXISTS idx_user_department_colors_dept ON user_department_colors(department_id)

-- STEP5: 部署1のデフォルトカラー設定
UPDATE departments SET color = '#4299E1' WHERE id = 1

-- STEP6: 部署2のデフォルトカラー設定
UPDATE departments SET color = '#48BB78' WHERE id = 2

-- STEP7: 部署3のデフォルトカラー設定
UPDATE departments SET color = '#ED8936' WHERE id = 3

-- STEP8: 部署4のデフォルトカラー設定
UPDATE departments SET color = '#9F7AEA' WHERE id = 4

-- STEP9: 部署5のデフォルトカラー設定
UPDATE departments SET color = '#38B2AC' WHERE id = 5