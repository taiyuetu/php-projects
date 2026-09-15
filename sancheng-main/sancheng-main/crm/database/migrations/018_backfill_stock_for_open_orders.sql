-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
--
-- 018 库存对账（方案 A 上线）：把「升级前已存在且未取消」的订单一次性从商品库存
-- 扣掉，让历史数据与运行时代码（订单非 cancelled 即占用库存）保持一致。
-- 只对已填过数值库存的商品生效：库存为 NULL/空（尚未启用库存管理）、或全新库
-- （还没有订单）时本语句不产生任何影响。
-- 上线后库存由代码维护（OrderItem::syncItems / Order::delete / AI 删除路径），
-- 本文件只应执行一次——重放会重复扣减，勿在已执行的库里改它。
UPDATE products
SET inventory = CAST(ROUND(CAST(inventory AS REAL) - COALESCE((
        SELECT SUM(oi.quantity)
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id = products.id
          AND o.status <> 'cancelled'
    ), 0), 4) AS TEXT)
WHERE inventory IS NOT NULL
  AND TRIM(inventory) <> ''
  AND inventory GLOB '*[0-9]*'
  AND inventory NOT GLOB '*[^0-9.eE+-]*';
