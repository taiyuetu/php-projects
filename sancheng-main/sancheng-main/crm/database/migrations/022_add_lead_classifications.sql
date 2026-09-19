-- 022：线索分类改造（纯加列，基线已含时自动跳过）
--   1) categories.type：区分商品分类(product)与线索来源分类(lead_source)
--   2) leads.source_category_id：线索挂到来源分类主数据
--   3) leads.grade：线索星级 A/B/C/D（热/温/冷/无效）
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

ALTER TABLE categories ADD COLUMN type TEXT NOT NULL DEFAULT 'product' CHECK (type IN ('product','lead_source'));
ALTER TABLE leads ADD COLUMN source_category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE leads ADD COLUMN grade TEXT CHECK (grade IS NULL OR grade IN ('A','B','C','D'));
