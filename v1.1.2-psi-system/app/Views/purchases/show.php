<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$purchaseAttrs = json_decode($purchase['attributes'] ?? '{}', true) ?: [];
$totalOrdered = (int)($purchase['total_ordered_qty'] ?? 0);
$totalArrived = (int)$purchase['total_arrived_qty'];
$remaining = max(0, $totalOrdered - $totalArrived);
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;">
        <div>
            <h2 style="margin-bottom:4px;">采购单 #<?= htmlspecialchars($purchase['invoice_no']) ?></h2>
            <p class="text-muted" style="margin-top:0;">供应商：<?= htmlspecialchars($purchase['supplier_name']) ?> · 日期：<?= htmlspecialchars($purchase['purchase_date']) ?></p>
        </div>
        <a href="<?= Router::url('/purchases') ?>" class="btn btn-secondary btn-sm">&larr; 返回采购列表</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:16px;padding:14px;background:#f9fafb;border-radius:8px;">
        <div>
            <div style="font-size:.8rem;color:#6b7280;">下单日期</div>
            <div style="font-weight:500;"><?= htmlspecialchars($purchase['purchase_date']) ?></div>
        </div>
        <div>
            <div style="font-size:.8rem;color:#6b7280;">预计到货</div>
            <div style="font-weight:500;"><?= $purchase['expected_arrival_date'] ? htmlspecialchars($purchase['expected_arrival_date']) : '<span style="color:#9ca3af;">—</span>' ?></div>
        </div>
        <div>
            <div style="font-size:.8rem;color:#6b7280;">采购数量</div>
            <div style="font-weight:500;"><?= $totalOrdered ?> units</div>
        </div>
        <div>
            <div style="font-size:.8rem;color:#6b7280;">累计到货</div>
            <div style="font-weight:500;color:#059669;"><?= $totalArrived ?> units</div>
        </div>
        <div>
            <div style="font-size:.8rem;color:#6b7280;">剩余数量</div>
            <div style="font-weight:500;color:<?= $remaining > 0 ? '#d97706' : '#059669' ?>;">
                <?= $remaining ?> 件
                <?= $remaining <= 0 ? ' ✅' : '' ?>
            </div>
        </div>
        <?php if (!empty($customFields)): ?>
            <?php foreach ($customFields as $key => $def): ?>
                <?php $val = $purchaseAttrs[$key] ?? ''; if ($val === '') continue; ?>
                <div>
                    <div style="font-size:.8rem;color:#6b7280;"><?= htmlspecialchars($def['label']) ?></div>
                    <div style="font-weight:500;">
                        <?php if (($def['type'] ?? 'text') === 'upload'): ?>
                            <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $val)): ?>
                                <img src="<?= Router::url('/' . $val) ?>" alt="" style="max-width:80px;max-height:50px;border-radius:3px;">
                            <?php else: ?>
                                <a href="<?= Router::url('/' . $val) ?>" target="_blank">📎 <?= htmlspecialchars(basename($val)) ?></a>
                            <?php endif; ?>
                        <?php elseif (($def['type'] ?? 'text') === 'textarea'): ?>
                            <?= nl2br(htmlspecialchars($val)) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($val) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (!empty($purchase['notes'])): ?>
    <div style="margin-top:12px;padding:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
        <div style="font-size:.8rem;color:#92400e;font-weight:600;margin-bottom:4px;">备注</div>
        <div style="color:#78350f;"><?= nl2br(htmlspecialchars($purchase['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Record New Arrival Form -->
    <?php if ($remaining > 0): ?>
    <div style="margin-top:20px;padding:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
        <h3 style="margin-top:0;margin-bottom:12px;font-size:1rem;color:#0369a1;">📦 Record Arrival (记录到货) — <?= $remaining ?> 件 remaining</h3>
        <form method="post" action="<?= Router::url('/purchases/' . $purchase['id'] . '/arrival') ?>">
            <?= $this->csrfField() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <div class="form-group">
                    <label style="font-size:.85rem;color:#374151;">到货日期</label>
                    <input type="date" name="arrival_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label style="font-size:.85rem;color:#374151;">到货数量 — 上限 <?= $remaining ?></label>
                    <input type="number" name="qty" min="1" max="<?= $remaining ?>" value="<?= min(1, $remaining) ?>" required>
                </div>
                <div class="form-group">
                    <label style="font-size:.85rem;color:#374151;">备注</label>
                    <input type="text" name="notes" placeholder="可选备注...">
                </div>
            </div>
            <div style="margin-top:12px;">
                <button type="submit" class="btn btn-primary">登记到货</button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div style="margin-top:20px;padding:16px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;">
        <h3 style="margin-top:0;margin-bottom:4px;font-size:1rem;color:#166534;">✅ 已全部到货</h3>
        <p style="margin:0;color:#15803d;font-size:.9rem;">所有订购数量已全部到货，无法继续登记到货。</p>
    </div>
    <?php endif; ?>

    <!-- Arrival History -->
    <?php if (!empty($purchase['arrivals'])): ?>
    <div style="margin-top:20px;">
        <h3 style="margin-bottom:12px;font-size:1rem;">📋 到货记录</h3>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>到货日期</th>
                    <th class="text-right">数量</th>
                    <th>备注</th>
                    <th>登记人</th>
                    <th>创建时间</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($purchase['arrivals'] as $idx => $arrival): ?>
                <tr>
                    <td class="text-muted"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($arrival['arrival_date']) ?></td>
                    <td class="text-right" style="font-weight:500;color:#059669;">+<?= (int)$arrival['qty'] ?></td>
                    <td class="text-muted"><?= htmlspecialchars($arrival['notes'] ?? '') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($arrival['created_by_name'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:.85rem;"><?= htmlspecialchars($arrival['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right"><strong>累计到货</strong></td>
                    <td class="text-right" style="font-weight:700;color:#059669;"><?= $totalArrived ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Purchase Items -->
    <div class="table-wrap" style="margin-top:20px;">
    <h3 style="margin-bottom:12px;font-size:1rem;">📝 Purchase Items (采购明细)</h3>
    <table>
        <thead><tr><th>SKU</th><th>商品</th><th class="text-right">订购数量</th><th class="text-right">单位成本</th><th class="text-right">小计</th></tr></thead>
        <tbody>
        <?php foreach ($purchase['items'] as $item): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($item['sku']) ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-right"><?= $item['qty'] ?></td>
                <td class="text-right"><?= money($item['unit_cost']) ?></td>
                <td class="text-right"><?= money($item['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="4" class="text-right"><strong>总计</strong></td><td class="text-right"><strong><?= money($purchase['total']) ?></strong></td></tr>
        </tfoot>
    </table>
    </div>
</div>
