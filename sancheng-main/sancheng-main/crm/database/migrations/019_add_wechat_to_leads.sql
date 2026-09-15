-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 线索的微信号。询盘进来时常只给微信（外贸场景里微信比邮箱常用），
-- 而 customers 早有 wechat、leads 却没有 —— 结果是“转商机”按钮建客户时
-- 没处可拷，客户的微信在线索阶段就已经丢了。这次补列同时修那条拷贝逻辑。
-- 该列同时声明在基线 schema.sql 的 leads 表里：
--   全新库由基线建好 → 本文件自动 skipped；
--   老库缺列 → 本文件真实执行 ALTER（见 database/migrations/README.md 的约定）。
ALTER TABLE leads ADD COLUMN wechat TEXT;
