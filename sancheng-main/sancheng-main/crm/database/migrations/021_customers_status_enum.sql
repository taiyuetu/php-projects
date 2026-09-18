-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
--
-- 客户状态从 2 种扩到 5 种：
--   active   活跃客户
--   one_time 一次性客户
--   inactive 非活跃客户
--   dormant  沉睡客户
--   lost     流失客户
--
-- 为什么是"重建表"而不是 ALTER：SQLite 不支持修改/删除已有的 CHECK 约束，只能走官方
-- 文档 "Making Other Kinds Of Table Schema Changes" 的路子：建新表 → 拷数据 → 换名。
-- 存量数据 active / inactive 在新枚举里含义不变，所以原样搬运，不做值转换。
--
-- 三个必须照做的细节（漏一个就出事）：
--   1. 关外键：DROP TABLE customers 时 leads / deals / orders / follow_ups / activities
--      的外键还指着它。PRAGMA foreign_keys 在事务里是空操作，所以必须在 BEGIN 之前。
--   2. legacy_alter_table=ON：默认（OFF）的 RENAME 会去重写"其它表里指向本表的
--      REFERENCES"，而此刻本表刚被 DROP、名字还不存在，正是最容易翻车的时刻。
--      这里要的就是"只改这张表的名字，别动别的表"。
--   3. 索引与触发器是表的附属物，会随 DROP TABLE 一起消失，必须原样重建 ——
--      而且不只是基线里的那几个：uidx_customers_public_code 是 007 建的（基线里没有），
--      漏了它唯一性就悄悄没了，PublicCodeTest 正是这么抓到本文件第一版的。
--      以后任何时候给 customers 加索引/触发器，都要同步补到这里。
--
-- 本文件含 PRAGMA，DbMigrator::execSql() 因此不会替我们包事务（它见到 PRAGMA 就
-- 放弃包事务），所以下面显式 BEGIN/COMMIT：要么整张表换成新结构，要么原样不动。
-- 开头的 DROP TABLE IF EXISTS 让本文件在"上次执行到一半失败"时也能重跑。

PRAGMA foreign_keys = OFF;
PRAGMA legacy_alter_table = ON;

BEGIN;

DROP TABLE IF EXISTS customers_new;

CREATE TABLE customers_new (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    -- 稳定编号：AI 与人工引用记录时用它，比裸 id 好念也好核对（见 Model::publicCode()）
    public_code               TEXT,
    name                      TEXT NOT NULL,
    company                   TEXT,
    email                     TEXT,
    phone                     TEXT,
    whatsapp                  TEXT,
    wechat                    TEXT,
    facebook                  TEXT,
    tiktok                    TEXT,
    website                   TEXT,
    source_country            TEXT,
    source_city               TEXT,
    address                   TEXT,
    shipping_address          TEXT,
    first_purchase_from_china INTEGER NOT NULL DEFAULT 0,
    has_import_capability     INTEGER NOT NULL DEFAULT 0,
    conversion_time           TEXT,
    status                    TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','one_time','inactive','dormant','lost')),
    owner_id                  INTEGER,
    notes                     TEXT,
    created_at                TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at                TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO customers_new (id, public_code, name, company, email, phone, whatsapp, wechat, facebook, tiktok, website,
                           source_country, source_city, address, shipping_address, first_purchase_from_china,
                           has_import_capability, conversion_time, status, owner_id, notes, created_at, updated_at)
SELECT id, public_code, name, company, email, phone, whatsapp, wechat, facebook, tiktok, website,
       source_country, source_city, address, shipping_address, first_purchase_from_china,
       has_import_capability, conversion_time, status, owner_id, notes, created_at, updated_at
FROM customers;

DROP TABLE customers;

ALTER TABLE customers_new RENAME TO customers;

CREATE INDEX IF NOT EXISTS idx_customers_status ON customers(status);

-- 来自增量 007（不在基线里）：public_code 的唯一性靠它保证
CREATE UNIQUE INDEX IF NOT EXISTS uidx_customers_public_code ON customers(public_code);

CREATE TRIGGER IF NOT EXISTS trg_customers_updated
    AFTER UPDATE ON customers
    FOR EACH ROW
BEGIN
    UPDATE customers SET updated_at = datetime('now') WHERE id = OLD.id;
END;

COMMIT;

PRAGMA legacy_alter_table = OFF;
PRAGMA foreign_keys = ON;
