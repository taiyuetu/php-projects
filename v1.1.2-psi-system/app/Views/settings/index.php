<?php

use App\Core\Router;
?>
<div class="card" style="max-width:720px;">
    <h2>系统设置</h2>
    <form method="post" action="<?= Router::url('/settings') ?>">
        <?= $this->csrfField() ?>

        <h3 style="margin-top:0;">🏢 应用信息</h3>
        <div class="form-group">
            <label>应用名称</label>
            <input type="text" name="app_name" required value="<?= htmlspecialchars($settings['app_name']) ?>">
        </div>

        <h3>🏭 公司信息</h3>
        <div class="form-group">
            <label>公司名称</label>
            <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name']) ?>">
        </div>
        <div class="form-group">
            <label>联系电话</label>
            <input type="text" name="company_phone" value="<?= htmlspecialchars($settings['company_phone']) ?>">
        </div>
        <div class="form-group">
            <label>公司邮箱</label>
            <input type="email" name="company_email" value="<?= htmlspecialchars($settings['company_email']) ?>">
        </div>
        <div class="form-group">
            <label>公司地址</label>
            <input type="text" name="company_address" value="<?= htmlspecialchars($settings['company_address']) ?>">
        </div>

        <h3>💰 货币与其他</h3>
        <div class="form-group">
            <label>货币符号</label>
            <input type="text" name="currency_symbol" required maxlength="8" style="max-width:120px;"
                   value="<?= htmlspecialchars($settings['currency_symbol']) ?>">
            <p class="text-muted" style="font-size:.85rem;margin:4px 0 0;">用于所有金额显示，例如 ¥、$、€。</p>
        </div>
        <div class="form-group">
            <label>单据备注</label>
            <textarea name="invoice_note" rows="3"
                      placeholder="显示在报表 / 单据底部的备注，可为空"><?= htmlspecialchars($settings['invoice_note']) ?></textarea>
        </div>

        <h3>👤 账户信息</h3>
        <div class="form-group">
            <label>姓名</label>
            <input type="text" name="user_name" required value="<?= htmlspecialchars($user['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="user_email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>新密码</label>
            <input type="password" name="user_password" autocomplete="new-password" minlength="6"
                   placeholder="留空则不修改密码">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>
</div>
