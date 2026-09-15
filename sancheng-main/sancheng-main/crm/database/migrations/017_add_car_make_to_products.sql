-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 商品制造商（当前库已用 schema-sync --apply 补上，这里让其它旧库升级时也能对齐）。
-- 该列同时声明在基线 schema.sql 的 products 表里：
--   全新库由基线建好 → 本文件自动 skipped；
--   老库缺列 → 本文件真实执行 ALTER（见 database/migrations/README.md 的约定）。
ALTER TABLE products ADD COLUMN car_make TEXT;
