-- 部署テーブル再作成スクリプト（colorカラム付き）

-- STEP1: 既存テーブルを削除
DROP TABLE departments;

-- STEP2: 新しいテーブルを作成（colorカラム付き）
CREATE TABLE departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    display_order INTEGER DEFAULT 0,
    color TEXT DEFAULT '#718096',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);