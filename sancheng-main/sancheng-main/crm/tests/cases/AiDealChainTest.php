<?php
/**
 * 商机/订单的关联列必须是「引用」，并且一个计划里要能“先建客户、再建商机”。
 *
 * 触发这一版的真实报障：一句「墨西哥客户发来轮毂单元400套询盘……新建商机」回报
 * `SQLSTATE[23000] FOREIGN KEY constraint failed`。两个原因叠在一起：
 *   1) Deal::$fields 把 customer_id 登记成 'int'，fieldsFor() 用它覆盖了外键应有的引用类型，
 *      于是提示词教模型写 CUS-000007、校验却要求整数；模型只能猜数字，猜空就撞库约束；
 *   2) 商机的客户本来就还不存在（询盘带来新客户），而系统没有“前一步刚建的那条”可引用，
 *      于是这条路怎么走都不通。
 * 这两件事都必须有断言钉住，不能靠下一次手改对。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function chainAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'name' => 'Admin', 'role' => 'admin'];
    return 1;
}

/** @return array{ok:bool,errors:array<int,string>,message:string} */
function runPlan(array $actions): array
{
    $v = Ai::validatePlan($actions, chainAdmin());
    $errors = [];
    foreach ($v['actions'] as $a) {
        foreach ((array) $a['errors'] as $e) {
            $errors[] = (string) $e;
        }
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'message' => implode(' | ', $errors)];
    }
    $r = Ai::execute($v['actions'], 1);
    $msgs = [];
    foreach ($r['results'] as $res) {
        $msgs[] = (string) ($res['message'] ?? '');
    }
    return ['ok' => $r['refused'] === 0, 'errors' => $msgs, 'message' => implode(' | ', $msgs)];
}

function lastDeal(): array
{
    return (array) (new Deal())->all('id DESC')[0];
}

// ---------------------------------------------------------------- 类型

function test_link_columns_stay_references_in_registered_tables(): void
{
    $tools = Ai::tools();
    // 商机：deals 有 Fields 注册表，正是被 'int' 覆盖掉的那张表
    assertEquals('customer_id', $tools['create_deal']['params']['customer_id']['type'],
        'create_deal.customer_id is a record reference, not a bare int');
    assertEquals('customer_id', $tools['update_deal']['params']['customer_id']['type'], 'same on update_deal');
    assertEquals('order_id', $tools['update_order']['params']['order_id']['type'], 'order refs too');
    // 而这条不能顺手把普通标量列也改掉：注册表的语义仍然优先
    assertEquals('money', $tools['create_deal']['params']['value']['type'], 'value stays money');
    assertEquals('enum', $tools['create_deal']['params']['stage']['type'], 'stage stays enum');
    // 三处同源：提示词里印出来的引用形式必须与校验用的类型一致
    // （toolsForPrompt 把 customer_id 缩成 cus，所以商机那行必须出现 customer_id:cus）
    $surface = implode("
", array_map(static fn($l) => (string) $l, Ai::toolsForPrompt()));
    assertContains('customer_id:cus!', $surface, 'the prompt line shows deal.customer_id as a customer reference');
}

function test_a_deal_can_be_created_against_an_existing_customer(): void
{
    $cust = (new Customer())->create(['name' => '既有客户']);
    $code = (new Customer())->codeOf((array) (new Customer())->find((int) $cust));

    // 编号写法 —— 提示词教的就是这一种
    $r = runPlan([['tool' => 'create_deal', 'args' => ['title' => '轮毂单元400套', 'customer_id' => $code, 'value' => '1000']]]);
    assertTrue($r['ok'], 'CUS-code reference works: ' . $r['message']);
    assertEquals((int) $cust, (int) lastDeal()['customer_id'], 'the deal is linked to that customer');

    // 数字写法仍然有效（老计划与 AI 直接给 id 的情况）
    $r2 = runPlan([['tool' => 'create_deal', 'args' => ['title' => '数字写法', 'customer_id' => (string) $cust]]]);
    assertTrue($r2['ok'], 'numeric reference still works: ' . $r2['message']);
    assertEquals((int) $cust, (int) lastDeal()['customer_id'], 'same target');
}

function test_a_missing_customer_is_refused_before_it_reaches_the_database(): void
{
    // 这条钉的是“报错必须是人能看懂的”，SQLSTATE 那句话用户无法处理，也无法据此决定下一步
    foreach (['CUS-999999', '999999'] as $ref) {
        $r = runPlan([['tool' => 'create_deal', 'args' => ['title' => '挂在空气上', 'customer_id' => $ref]]]);
        assertTrue(!$r['ok'], "unresolvable {$ref} must be refused");
        assertContains('找不到对应记录', $r['message'], 'and it says the record was not found');
        assertContains('search_records', $r['message'], 'and it points at the way to get a real code');
        assertTrue(!str_contains($r['message'], 'SQLSTATE'), 'no raw database error is shown to the user');
    }
    assertEquals(0, (new Deal())->count('title = :t', [':t' => '挂在空气上']), 'nothing was written');
}

// ---------------------------------------------------------------- @N 引用

function test_a_new_customer_and_its_deal_are_one_instruction(): void
{
    $r = runPlan([
        ['tool' => 'create_customer', 'args' => [
            'name' => '低压的', 'source_country' => 'Mexico', 'phone' => '03245678821',
            'whatsapp' => '03245678821', 'wechat' => '03245678821']],
        ['tool' => 'create_deal', 'args' => ['title' => '轮毂单元400套询盘', 'customer_id' => '@1', 'value' => '0']],
    ]);
    assertTrue($r['ok'], 'the two-step plan runs: ' . $r['message']);

    $deal = lastDeal();
    $cust = (array) (new Customer())->findBy('name', '低压的');
    assertTrue($cust !== [], 'the customer was created');
    assertEquals((int) $cust['id'], (int) $deal['customer_id'], 'and the deal hangs off it');
    assertEquals('03245678821', (string) $cust['whatsapp'], 'contact fields came along');
    assertContains('DEAL-', (string) $deal['public_code'], 'the deal got its stable code');
    // 服务器填的是真实 id，@1 不会以字符串形式漏进数据库
    assertTrue(is_numeric((string) $deal['customer_id']), 'the reference was resolved to an id before writing');
}

function test_pending_references_cannot_be_abused(): void
{
    $cust = (new Customer())->create(['name' => '真实客户']);

    // 1) 向后引用（指向自己或更晚的步骤）：一个循环就能把类型检查绕过去，所以只许向前
    $back = runPlan([['tool' => 'create_deal', 'args' => ['title' => '倒着引', 'customer_id' => '@2']]]);
    assertTrue(!$back['ok'], 'a self/forward reference is refused');
    assertContains('只能引用排在本动作之前', $back['message'], 'with the reason');

    // 2) 类型不匹配：@1 建的是商机，就不能当客户用
    $wrong = runPlan([
        ['tool' => 'create_customer', 'args' => ['name' => '给客户建个商机']],
        ['tool' => 'create_deal', 'args' => ['title' => 'X', 'customer_id' => '@1']],   // 这里 @1 是客户，合法
    ]);
    assertTrue($wrong['ok'], 'a matching @1 is accepted (control case)');
    $mismatch = runPlan([
        ['tool' => 'create_deal', 'args' => ['title' => '先建商机', 'customer_id' => (string) $cust]],
        ['tool' => 'add_follow_up', 'args' => ['title' => '跟进', 'customer_id' => '@1']],  // 第1步建的是商机
    ]);
    assertTrue(!$mismatch['ok'], 'a deal cannot be used where a customer is required');
    assertContains('与这里的类型不匹配', $mismatch['message'], 'and it names the mismatch');

    // 3) 引用的那一步失败了：不能让后面的动作挂到一个不存在的 id 上
    $broken = runPlan([
        ['tool' => 'create_customer', 'args' => ['name' => '']],                        // 必填列为空 → 那一步被拒
        ['tool' => 'create_deal', 'args' => ['title' => '挂在不存在的客户上', 'customer_id' => '@1']],
    ]);
    assertTrue(!$broken['ok'], 'referencing a step that failed validation is refused');
    assertContains('第 1 步本身有问题', $broken['message'], 'and the message points at that step');
}

function test_the_preview_explains_a_pending_reference(): void
{
    $plan = Ai::validatePlan([
        ['tool' => 'create_customer', 'args' => ['name' => '低压的']],
        ['tool' => 'create_deal', 'args' => ['title' => '轮毂单元400套', 'customer_id' => '@1']],
    ], chainAdmin());
    $shown = Ai::argText('@1', $plan['actions']);
    assertContains('@1', $shown, 'the placeholder is still visible');
    assertContains('第 1 步新建的客户', $shown, 'and it is spelled out for the human at the confirm gate');
    assertEquals('CUS-000001', Ai::argText('CUS-000001', $plan['actions']), 'ordinary values pass through');
}

/**
 * 用户的原话场景：一句「…询价…新建商机」，模型先建线索再拿 @1 挂商机。
 * 服务端必须把这条路跑通（而不是让用户看着一个注定失败的计划）。
 */
function test_a_lead_plus_deal_plan_becomes_customer_plus_deal(): void
{
    $plan = [
        ['tool' => 'create_lead', 'args' => [
            'title' => '上海外贸公司 - 道奇车轮毂单元配件200件询价', 'company' => '上海外贸公司',
            'contact_name' => '赵小姐', 'phone' => '09873211234', 'wechat' => '09873211234',
            'source' => 'inquiry', 'source_country' => 'China', 'source_city' => 'Shanghai']],
        ['tool' => 'create_deal', 'args' => ['title' => '道奇车轮毂单元配件200件', 'customer_id' => '@1', 'stage' => 'open']],
    ];

    [$rewritten, $note] = Ai::routeLeadIntoCustomerForDeal($plan);
    assertContains('商机必须属于客户', $note, 'the rewrite says why it happened');
    assertEquals('create_customer', $rewritten[0]['tool'], 'step 1 became a customer');
    assertEquals('create_deal', $rewritten[1]['tool'], 'step 2 untouched');
    assertEquals('@1', $rewritten[1]['args']['customer_id'], 'the reference still points at step 1 (序号不变)');
    assertEquals('赵小姐', $rewritten[0]['args']['name'], '联系人成了客户名（customers.name 必填）');
    assertEquals('09873211234', $rewritten[0]['args']['wechat'], '微信跟着过去了，不再丢');
    assertEquals('09873211234', $rewritten[0]['args']['phone'], 'phone too');
    assertEquals('上海外贸公司', $rewritten[0]['args']['company'], 'company kept');
    // 线索独有的列不能混进客户表（SQLite 会直接报 no such column）
    foreach (['title', 'lead_time', 'source', 'status', 'value'] as $col) {
        assertTrue(!array_key_exists($col, $rewritten[0]['args']), "lead-only column {$col} is not carried over");
    }

    $r = runPlan($rewritten);
    assertTrue($r['ok'], 'the rewritten plan actually runs: ' . $r['message']);
    $cust = (array) (new Customer())->findBy('name', '赵小姐');
    assertTrue($cust !== [], 'customer created');
    assertEquals('09873211234', (string) $cust['wechat'], 'and the WeChat number is really in the database');
    assertEquals((int) $cust['id'], (int) lastDeal()['customer_id'], 'the deal hangs off that customer');
}

function test_the_rewrite_leaves_sane_plans_alone(): void
{
    // 只建线索 → 不改（否则就把用户要的线索抹掉了）
    [$same, $note] = Ai::routeLeadIntoCustomerForDeal(
        [['tool' => 'create_lead', 'args' => ['title' => '只要一条线索', 'contact_name' => '某人']]]);
    assertEquals('', $note, 'a lead on its own is not rewritten');
    assertEquals('create_lead', $same[0]['tool'], 'and stays a lead');

    // 商机挂真客户编号 → 不改
    [$same2, $note2] = Ai::routeLeadIntoCustomerForDeal([
        ['tool' => 'create_lead', 'args' => ['title' => '线索']],
        ['tool' => 'create_deal', 'args' => ['title' => '商机', 'customer_id' => 'CUS-000001']]]);
    assertEquals('', $note2, 'an explicit CUS- code is what we want; no rewrite');
    assertEquals('create_lead', $same2[0]['tool'], 'step 1 still a lead');

    // 商机挂真线索的 ID（数字）也不能触发改写：那只能靠校验报错
    [$same3, $note3] = Ai::routeLeadIntoCustomerForDeal([
        ['tool' => 'create_deal', 'args' => ['title' => 'X', 'customer_id' => '1']]]);
    assertEquals('', $note3, 'plain ids are not rewritten');
}

function test_a_lead_code_used_as_a_customer_explains_the_mistake(): void
{
    $leadId = (new Lead())->create(['title' => '要被误用的线索', 'contact_name' => '某人']);
    $code = (new Lead())->codeOf((array) (new Lead())->find((int) $leadId));

    $r = runPlan([['tool' => 'create_deal', 'args' => ['title' => '挂错了对象', 'customer_id' => $code]]]);
    assertTrue(!$r['ok'], 'a LEAD code cannot be a customer');
    assertContains('是线索的编号', $r['message'], 'and the message names the mix-up, not just “not found”');
    assertContains('这里要的是客户编号', $r['message'], 'and says what belongs here');
    assertContains('@N', $r['message'], 'and points at the two-step way to do it');
    assertTrue(!str_contains($r['message'], 'SQLSTATE'), 'still no raw database error');
}

function test_wechat_is_a_real_lead_field_now(): void
{
    // 微信以前在 leads 表里没有列：customers 有、leads 没有，于是“转商机”建客户时
    // 无处可拷 —— 线索阶段就把客户的微信丢了。这条钉住列存在、AI 能写、页面表单有、
    // 并且 LeadController@convert 真的把它拷给客户。
    $cols = array_column(Schema::columns('leads'), 'name');
    assertTrue(in_array('wechat', $cols, true), 'leads.wechat exists');
    assertTrue(isset(Ai::tools()['create_lead']['params']['wechat']), 'and the AI can write it');

    $id = (new Lead())->create(['title' => '带微信的线索', 'wechat' => 'wx-zhao']);
    assertEquals('wx-zhao', (string) (new Lead())->find((int) $id)['wechat'], 'it persists through the model');

    $form = (string) file_get_contents(BASE_PATH . '/app/views/leads/_form.php');
    assertContains('name="wechat"', $form, 'the lead form has the field');
    $convert = (string) file_get_contents(BASE_PATH . '/app/controllers/LeadController.php');
    assertContains("'wechat'       => \$lead['wechat'] ?? null", $convert,
        'and 线索转商机 copies it onto the customer it creates');
}

function test_the_prompt_teaches_the_reference_it_honours(): void
{
    // 校验认 @N、提示词却不教，模型就永远不会用它；反过来也一样是 bug。两处必须同时存在。
    $prompt = (string) Ai::systemPrompt();
    assertContains('@1', $prompt, 'the system prompt teaches @N references');
    assertContains('不要猜一个 ID', $prompt, 'and still forbids guessing ids');
    assertContains('新建商机', $prompt, 'and names the user-says-deal case explicitly');
    assertContains('商机只认客户', $prompt, 'and teaches that a deal cannot hang off a lead');
    assertContains('先 create_customer', $prompt, 'naming the step that must come first');
}

/**
 * 用户报障：「新建商机：不接受参数 notes」。备注是后来补上的列，
 * 而 AI 的参数清单由表结构生成 —— 这里验“加了列就真能写”，不靠手写清单同步。
 */
function test_the_ai_can_write_the_deal_note(): void
{
    assertTrue(isset(Ai::tools()['create_deal']['params']['notes']), 'create_deal lists notes');
    assertTrue(isset(Ai::tools()['update_deal']['params']['notes']), 'update_deal lists it too');
    assertEquals('备注', Ai::tools()['create_deal']['params']['notes']['label'], 'with the Chinese label');

    $cust = (new Customer())->create(['name' => '上海外贸']);
    $r = runPlan([['tool' => 'create_deal', 'args' => [
        'title' => '道奇车轮毂单元配件200件', 'customer_id' => (string) $cust,
        'notes' => '赵小姐，电话/微信 09873211234，要 9 月前交货']]]);
    assertTrue($r['ok'], 'the plan with a note runs: ' . $r['message']);
    assertContains('9 月前交货', (string) lastDeal()['notes'], 'and the note is really in the database');

    // 改写后的计划（线索→客户那一条路）也不得把备注弄丢
    [$plan, $note] = Ai::routeLeadIntoCustomerForDeal([
        ['tool' => 'create_lead', 'args' => ['title' => '询价', 'contact_name' => '赵小姐', 'notes' => '细节在线索备注里']],
        ['tool' => 'create_deal', 'args' => ['title' => '商机', 'customer_id' => '@1', 'notes' => '商机自己的备注']],
    ]);
    assertContains('新建客户', $note, 'the rewrite still fires');
    assertEquals('细节在线索备注里', $plan[0]['args']['notes'], 'the lead note travels onto the customer');
    assertEquals('商机自己的备注', $plan[1]['args']['notes'], 'and the deal keeps its own note');

    // 没这一列的表不能假装能写（列没了就会撞 no such column）
    assertTrue(!isset(Ai::tools()['create_deal']['params']['lost_reason']), 'deals has no lost_reason column');
}

runCase();
