<?php
/**
 * 库存自动扣减（方案 A）回归测试。
 *
 * 业务规则（方案 A：订单只要不是 cancelled 就占用库存）：
 *   1. 保存订单明细（新建/编辑/商机成交转单/AI set_order_items 同路）→ 按明细扣库存；
 *   2. 改数量 → 按新旧差值净调整；
 *   3. 清空明细 / 删除订单 → 占用全部加回；
 *   4. 订单改成 cancelled（同一请求带 prevStatus 快照）→ 释放；恢复成其它状态 → 重新占用；
 *   5. 还没填过库存数值的商品（inventory NULL/空）不自动扣，不产生负数脏数据。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function stockCust(): int
{
    $name = '库存客户-' . bin2hex(random_bytes(3));
    return (new Customer())->create(['name' => $name, 'status' => 'active']);
}

function stockProd(string $inventory): int
{
    $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));
    return (new Product())->create([
        'name'      => '库存商品-' . bin2hex(random_bytes(3)),
        'sku'       => $sku,
        'unit'      => '件',
        'price'     => 10,
        'status'    => 'active',
        'inventory' => $inventory,
    ]);
}

function stockOrder(int $customerId, string $status = 'pending'): int
{
    return (new Order())->create([
        'order_number'   => 'T-' . bin2hex(random_bytes(3)),
        'customer_id'    => $customerId,
        'title'          => '库存测试单',
        'status'         => $status,
        'payment_status' => 'unpaid',
        'order_date'     => date('Y-m-d'),
    ]);
}

function stockLine(int $productId, float $qty): array
{
    $p = (new Product())->find($productId);
    return [
        'product_id'   => $productId,
        'product_name' => (string) ($p['name'] ?? ''),
        'sku'          => (string) ($p['sku'] ?? ''),
        'quantity'     => $qty,
        'unit_price'   => 10,
        'unit'         => (string) ($p['unit'] ?? '件'),
    ];
}

function stockOf(int $productId): float
{
    return (new Product())->stockValue($productId);
}

function test_order_create_deducts_and_edit_adjusts_by_delta(): void
{
    $pid = stockProd('10');
    $oid = stockOrder(stockCust());

    (new OrderItem())->syncItems($oid, [stockLine($pid, 3)]);
    assertEquals(7, (int) round(stockOf($pid)), '建单扣 3：10 → 7');

    // 改数量 3 → 1：净调整 −2
    (new OrderItem())->syncItems($oid, [stockLine($pid, 1)]);
    assertEquals(9, (int) round(stockOf($pid)), '改量 3→1：7 → 9');

    // 加一行、总量变多：净调整继续正确
    (new OrderItem())->syncItems($oid, [stockLine($pid, 4)]);
    assertEquals(6, (int) round(stockOf($pid)), '改量 1→4：9 → 6');
}

function test_order_delete_restores_stock(): void
{
    $pid = stockProd('10');
    $oid = stockOrder(stockCust());
    (new OrderItem())->syncItems($oid, [stockLine($pid, 3)]);
    assertEquals(7, (int) round(stockOf($pid)), '扣减生效');

    (new Order())->delete($oid);
    assertEquals(10, (int) round(stockOf($pid)), '删单还原：7 → 10');
}

function test_cancel_releases_and_reactivate_commits(): void
{
    $pid = stockProd('10');
    $oid = stockOrder(stockCust());
    (new OrderItem())->syncItems($oid, [stockLine($pid, 3)]);
    assertEquals(7, (int) round(stockOf($pid)), '初始占用');

    // 模拟编辑表单把订单改成取消（先改行状态，再带 prevStatus 快照重写明细）
    (new Order())->update($oid, ['status' => 'cancelled']);
    (new OrderItem())->syncItems($oid, [stockLine($pid, 3)], 'pending');
    assertEquals(10, (int) round(stockOf($pid)), '取消释放：7 → 10');

    // 恢复成在途（同一请求 prevStatus=cancelled → 重新占用）
    (new Order())->update($oid, ['status' => 'confirmed']);
    (new OrderItem())->syncItems($oid, [stockLine($pid, 3)], 'cancelled');
    assertEquals(7, (int) round(stockOf($pid)), '恢复占用：10 → 7');
}

function test_clearing_all_lines_restores(): void
{
    $pid = stockProd('10');
    $oid = stockOrder(stockCust());
    (new OrderItem())->syncItems($oid, [stockLine($pid, 2)]);
    assertEquals(8, (int) round(stockOf($pid)), '占用 2');

    (new OrderItem())->syncItems($oid, []);
    assertEquals(10, (int) round(stockOf($pid)), '清空明细还原');
}

function test_untracked_inventory_is_never_touched(): void
{
    // 不填库存（NULL = 还没启用库存管理）
    $pid = (new Product())->create([
        'name'   => '未管库存商品-' . bin2hex(random_bytes(3)),
        'unit'   => '件',
        'price'  => 5,
        'status' => 'active',
    ]);
    $oid = stockOrder(stockCust());
    (new OrderItem())->syncItems($oid, [stockLine($pid, 3)]);
    (new OrderItem())->syncItems($oid, []);

    $row = (new Product())->find($pid);
    assertEquals(null, $row['inventory'] ?? null, 'NULL 库存不被自动扣减/污染');

    // 手填后再开单才生效
    (new Product())->update($pid, ['inventory' => '10']);
    $oid2 = stockOrder(stockCust());
    (new OrderItem())->syncItems($oid2, [stockLine($pid, 3)]);
    assertEquals(7, (int) round(stockOf($pid)), '手填 10 后订单扣 3 → 7');
    (new Order())->delete($oid2);
    assertEquals(10, (int) round(stockOf($pid)), '删单还原手填库存');
}

function test_ai_style_status_flip_uses_release_commit(): void
{
    // AI 单独改状态不重写明细，走 releaseOrderStock / commitOrderStock（Ai::runModify）
    $pid = stockProd('10');
    $oid = stockOrder(stockCust());
    (new OrderItem())->syncItems($oid, [stockLine($pid, 4)]);
    assertEquals(6, (int) round(stockOf($pid)), '占用 4');

    (new Order())->update($oid, ['status' => 'cancelled']);
    OrderItem::releaseOrderStock($oid, true);
    assertEquals(10, (int) round(stockOf($pid)), 'AI 取消 → 释放');

    OrderItem::releaseOrderStock($oid);                       // 幂等：已取消不重复加
    assertEquals(10, (int) round(stockOf($pid)), '重复释放无副作用');

    (new Order())->update($oid, ['status' => 'confirmed']);
    OrderItem::commitOrderStock($oid);
    assertEquals(6, (int) round(stockOf($pid)), 'AI 恢复 → 重新占用');
}

runCase();
