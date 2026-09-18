<?php use App\Core\Router; ?>
<div class="card" style="max-width:700px;">
    <h2>从CSV导入供应商</h2>
    <p class="text-muted" style="margin-top:0;">
        上传CSV文件即可批量创建或更新供应商。仅需 <strong>name</strong> 一列为必填。
    </p>

    <form method="post" action="<?= Router::url('/suppliers/import') ?>" enctype="multipart/form-data">
        <?= $this->csrfField() ?>

        <div class="form-group">
            <label>CSV文件</label>
            <input type="file" name="import_file" accept=".csv,text/csv" required>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="update_existing" value="1" style="width:auto;">
                Update existing suppliers (by name) instead of skipping them
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">上传并导入</button>
            <a href="<?= Router::url('/suppliers') ?>" class="btn btn-secondary">取消</a>
        </div>
    </form>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
        <h3 style="margin:0 0 8px;">CSV期望的列</h3>
        <p class="text-muted" style="margin-top:0;">第一行必须是表头。列顺序不限，但列名必须匹配（不区分大小写）：</p>
        <table>
            <thead><tr><th>列名</th><th>是否必填</th><th>备注</th></tr></thead>
            <tbody>
                <tr><td><code>name</code></td><td>是</td><td>供应商名称</td></tr>
                <tr><td><code>phone</code></td><td>否</td><td></td></tr>
                <tr><td><code>email</code></td><td>否</td><td></td></tr>
                <tr><td><code>address</code></td><td>否</td><td></td></tr>
            </tbody>
        </table>

        <p class="text-muted" style="margin-top:16px;">
            提示：请先使用 <a href="<?= Router::url('/suppliers/export') ?>">导出CSV</a> first to get a correctly
            formatted file, then edit it and re-import.
        </p>
    </div>
</div>
