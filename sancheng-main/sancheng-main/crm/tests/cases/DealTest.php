<?php
/** Deal model tests — pipeline queries, archive lifecycle, order lookups.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function seedCustomer(string $name): array
{
    $c = new Customer();
    $id = $c->create(['name' => $name, 'status' => 'active']);
    return $c->find((int) $id);
}

function seedDeal(int $customerId, array $overrides = []): array
{
    $d = new Deal();
    $id = $d->create(array_merge([
        'title'       => 'Deal ' . bin2hex(random_bytes(3)),
        'customer_id' => $customerId,
        'value'       => 10000,
        'stage'       => 'open',
        'owner_id'    => 1,
    ], $overrides));
    return $d->find((int) $id);
}

function test_deal_stage_aggregates(): void
{
    $cust = seedCustomer('Agg C');
    $d = new Deal();
    seedDeal((int) $cust['id'], ['stage' => 'proposal', 'value' => 3000]);
    seedDeal((int) $cust['id'], ['stage' => 'negotiation', 'value' => 7000]);
    seedDeal((int) $cust['id'], ['stage' => 'closed_won', 'value' => 9999]);

    assertEquals(3000.0, $d->sumValueByStage('proposal'), 'sum by stage');
    assertEquals(10000.0, $d->openPipelineValue(), 'open pipeline excludes won');
}

function test_deal_archive_unarchive(): void
{
    $cust = seedCustomer('Arc C');
    $row = seedDeal((int) $cust['id']);
    $d = new Deal();

    // Not archived initially, appears in board.
    $board = $d->allWithCustomer();
    assertEquals(1, count($board), 'deal on board');
    assertEquals('Arc C', $board[0]['customer_name'], 'customer joined');

    assertTrue($d->archive((int) $row['id']), 'archive ok');
    $archived = $d->find((int) $row['id']);
    assertEquals(1, (int) $archived['archived'], 'archived flag set');
    assertTrue(!empty($archived['archived_at']), 'archived_at set');
    assertEquals(0, count($d->allWithCustomer()), 'gone from board');
    assertEquals(1, count($d->allArchived()), 'appears in archived list');

    assertTrue($d->unarchive((int) $row['id']), 'unarchive ok');
    assertEquals(0, (int) $d->find((int) $row['id'])['archived'], 'back on board');
}

function test_unarchive_lost_deal_returns_to_open(): void
{
    $cust = seedCustomer('Reopen C');
    $row = seedDeal((int) $cust['id'], ['stage' => 'closed_lost']);
    $d = new Deal();

    // Mark closed_lost -> archived (as the controller update flow does).
    assertTrue($d->archive((int) $row['id']), 'lost deal archived');
    $lost = $d->find((int) $row['id']);
    assertEquals('closed_lost', $lost['stage'], 'stage is closed_lost');
    assertEquals(1, (int) $lost['archived'], 'archived');

    // Unarchive should send it back to "进行中" (open), not the lost column.
    assertTrue($d->unarchive((int) $row['id']), 'unarchive ok');
    $back = $d->find((int) $row['id']);
    assertEquals('open', $back['stage'], 'restored deal is open (进行中)');
    assertEquals(0, (int) $back['archived'], 'restored deal not archived');
    assertEquals(null, $back['archived_at'], 'archived_at cleared');
    assertEquals(null, $back['stage_closed_lost_at'], 'closed_lost_at cleared');
    assertTrue(!empty($back['stage_open_at']), 'stage_open_at re-recorded');

    // Now visible on the board again under open.
    $board = $d->allWithCustomer();
    assertEquals(1, count($board), 'deal back on board');
    assertEquals('open', $board[0]['stage'], 'board row stage is open');
}

/** 商机关键词搜索：按标题、按 JOIN 进来的客户名，归档页同样支持 */
function test_deal_keyword_search(): void
{
    $alpha = seedCustomer('Alpha Trading Co');
    $beta  = seedCustomer('Beta Logistics Ltd');
    $d = new Deal();
    seedDeal((int) $alpha['id'], ['title' => 'Aluminium coils', 'value' => 5000]);
    seedDeal((int) $alpha['id'], ['title' => 'Steel sheets', 'value' => 8000]);
    seedDeal((int) $beta['id'],  ['title' => 'Aluminium rails', 'value' => 2000]);

    // 按标题关键词
    $byTitle = $d->allWithCustomer('Steel');
    assertEquals(1, count($byTitle), '按商机标题搜索');
    assertEquals('Steel sheets', $byTitle[0]['title'], '命中正确商机');

    // 按 JOIN 进来的客户名，命中该客户名下全部商机
    $byCustomer = $d->allWithCustomer('Alpha Trading');
    assertEquals(2, count($byCustomer), '按客户名搜索命中其名下两条');
    foreach ($byCustomer as $row) {
        assertEquals('Alpha Trading Co', $row['customer_name'], '命中的都是 Alpha 的商机');
    }

    // 归档列表同样支持搜索
    $row = seedDeal((int) $beta['id'], ['title' => 'Warehouse racks']);
    assertTrue($d->archive((int) $row['id']), 'archive ok');
    $archived = $d->allArchived('Beta Logistics');
    assertEquals(1, count($archived), '归档页可按客户名搜');
    assertEquals('Warehouse racks', $archived[0]['title'], '命中刚归档的那条');
    assertEquals(0, count($d->allWithCustomer('Warehouse racks')), '已归档的商机不出现在看板搜索结果里');
    // 归档前同客户的那条未归档商机，看板仍能搜到
    assertEquals(1, count($d->allWithCustomer('Aluminium rails')), '未归档的同类商机仍可搜到');
}

function test_deal_orders_lookup(): void
{
    $cust = seedCustomer('Ord C');
    $deal = seedDeal((int) $cust['id']);

    $orderModel = new Order();
    $orderId = $orderModel->create([
        'order_number' => $orderModel->generateOrderNumber(),
        'deal_id'      => (int) $deal['id'],
        'customer_id'  => (int) $cust['id'],
        'title'        => 'Order for deal',
        'amount'       => 100,
        'status'       => 'pending',
        'owner_id'     => 1,
    ]);

    $orders = (new Deal())->orders((int) $deal['id']);
    assertEquals(1, count($orders), 'orders() finds the deal order');
    assertEquals((int) $orderId, (int) $orders[0]['id'], 'correct order returned');
    assertEquals('Ord C', $orders[0]['customer_name'], 'customer joined on order');
}

/**
 * 备注。customers / leads / orders 一直都有 notes，商机是四张业务表里唯一没有的 ——
 * 于是“把询价细节记在商机上”无处可写（AI 直接报「不接受参数 notes」）。
 * 这条钉住列 + 表单/搜索/AI 三处接线（约定是“加字段只改注册表一处”，那就得验证三处真的都通了）。
 */
function test_deal_notes_round_trip_and_wiring(): void
{
    $cols = array_column(Schema::columns('deals'), 'name');
    assertTrue(in_array('notes', $cols, true), 'deals.notes exists in the schema');
    $t = new Deal();

    // 存与取
    $cust = seedCustomer('Notes Trading');
    $deal = seedDeal((int) $cust['id'], ['title' => '道奇车轮毂单元 200件', 'notes' => "赵小姐 09873211234\n要求 9 月前交货，含微信联系"]);
    assertEquals("赵小姐 09873211234\n要求 9 月前交货，含微信联系", (string) $deal['notes'],
        'notes persists verbatim (multi-line included)');
    $again = (new Deal())->find((int) $deal['id']);
    assertContains('9 月前交货', (string) $again['notes'], 'and reads back');

    // 改：不传 notes 就不得动它（update 只写给出的列）
    (new Deal())->update((int) $deal['id'], ['value' => 1234]);
    assertContains('9 月前交货', (string) (new Deal())->find((int) $deal['id'])['notes'],
        'an unrelated update leaves the note alone');

    // 关键词搜索不扩：deals 的搜索列是有意只覆盖「标题 + 客户名」（见 Deal::$searchable 上的注释），
    // 询价细节的正当归处是商机详情页的跟进记录。所以备注能写能看，但不进 LIKE 清单 ——
    // 长文本进关键词搜索会让“搜客户名”这个主用法噪起来。
    $hit = (new Deal())->allWithCustomer('道奇');
    assertEquals(1, count($hit), '标题搜索照常');
    assertContains('09873211234', (string) $hit[0]['notes'], '号码在备注里，从结果行就能读到');
    assertEquals(0, count((new Deal())->allWithCustomer('09873211234')), '但不拿备注当关键词查');

    // 表单：没列在精修布局里的列，由自动字段区接住（约定见 partial 顶部注释）
    assertTrue(array_key_exists('notes', (new Deal())->autoFormFields()),
        'the deal form renders it through the auto-field region');
    // 详情页真把它印出来
    assertContains('$deal[\'notes\']', (string) file_get_contents(BASE_PATH . '/app/views/deals/show.php'),
        'the detail page shows the note');

    // 四张业务表同构：别再出现“只有商机没备注”这种差异
    foreach (['customers', 'leads', 'orders', 'deals'] as $tb) {
        assertTrue(in_array('notes', array_column(Schema::columns($tb), 'name'), true),
            "{$tb}.notes exists");
    }
}

runCase();
