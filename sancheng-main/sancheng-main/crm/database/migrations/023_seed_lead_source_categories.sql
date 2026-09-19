-- 023：线索分类收尾 —— 新列索引 + 线索来源分类默认种子。
-- 全部幂等（CREATE INDEX IF NOT EXISTS / INSERT OR IGNORE），
-- 新库（基线已含列）与老库（022 已补列）都能安全执行。
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

CREATE INDEX IF NOT EXISTS idx_categories_type ON categories(type);
CREATE INDEX IF NOT EXISTS idx_leads_grade ON leads(grade);
CREATE INDEX IF NOT EXISTS idx_leads_source_cat ON leads(source_category_id);

INSERT OR IGNORE INTO categories (name, type, sort_order) VALUES
('展会',        'lead_source', 1),
('B2B 平台',    'lead_source', 2),
('官网询盘',    'lead_source', 3),
('社交媒体',    'lead_source', 4),
('老客户推荐',  'lead_source', 5),
('广告投放',    'lead_source', 6),
('其他',        'lead_source', 99);
