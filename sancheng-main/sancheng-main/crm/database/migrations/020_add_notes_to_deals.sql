-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 商机备注。customers / leads / orders 一直都有 notes，deals 是四张业务表里唯一没有的，
-- 于是「把询价细节记在商机上」无处可写（AI 也直接报「不接受参数 notes」）。
-- 该列同时声明在基线 schema.sql 的 deals 表里：
--   全新库由基线建好 → 本文件自动 skipped；
--   老库缺列 → 本文件真实执行 ALTER（见 database/migrations/README.md 的约定）。
-- 没跑本迁移前新列不存在也不报错：Fields::columns() 以真实表结构为准，
-- 注册表里多出来的列会被跳过（表单不显示、AI 参数里也没有）。
ALTER TABLE deals ADD COLUMN notes TEXT;
