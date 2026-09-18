<?php use App\Core\Router; ?>
<div class="card" style="max-width:700px;">
    <h2>从CSV导入产品</h2>
    <p class="text-muted" style="margin-top:0;">
        上传CSV文件即可批量创建或更新产品。仅需 <strong>sku</strong> 和 <strong>name</strong> 两列为必填。
    </p>

    <form method="post" action="<?= Router::url('/products/import') ?>" enctype="multipart/form-data">
        <?= $this->csrfField() ?>

        <div class="form-group">
            <label>CSV文件 <span class="text-muted">（或包含图片的ZIP压缩包）</span></label>
            <input type="file" name="import_file" accept=".csv,.zip,text/csv,application/zip" required>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="update_existing" value="1" style="width:auto;">
                Update existing products (by SKU) instead of skipping them
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">上传并导入</button>
            <a href="<?= Router::url('/products') ?>" class="btn btn-secondary">取消</a>
        </div>
    </form>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
        <h3 style="margin:0 0 8px;">导入产品图片</h3>
        <p class="text-muted" style="margin-top:0;">
            如需自动关联产品图片，请将CSV与 <code>images</code> 文件夹一起打包为 <code>.zip</code> 文件后上传：
        </p>
        <pre style="background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius);padding:12px;font-size:0.85rem;overflow-x:auto;">your-import.zip
├── products.csv
└── images/
    ├── tqb0001-1.jpg
    ├── tqb0001(1).jpg
    ├── tqb0001-2.png
    └── tqb0002.jpg</pre>
        <p class="text-muted">
            文件名以产品 <strong>SKU</strong> 开头（后面跟 <code>-</code>、<code>_</code>、<code>(</code>、<code>.</code>、空格或直接结束）的图片会自动加入该产品的图库。支持格式：JPG、PNG、GIF、WebP、BMP。
        </p>
    </div>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
        <h3 style="margin:0 0 8px;">CSV期望的列</h3>
        <p class="text-muted" style="margin-top:0;">第一行必须是表头。列顺序不限，但列名必须匹配（不区分大小写）：</p>
        <table>
            <thead>
                <tr><th>列名</th><th>是否必填</th><th>备注</th></tr>
            </thead>
            <tbody>
                <tr><td><code>sku</code></td><td>是</td><td>产品唯一编码</td></tr>
                <tr><td><code>name</code></td><td>是</td><td>产品名称</td></tr>
                <tr><td><code>category</code></td><td>否</td><td>分类名称 — 不存在时自动创建</td></tr>
                <tr><td><code>unit</code></td><td>否</td><td>默认为 <code>pcs</code></td></tr>
                <tr><td><code>cost_price</code></td><td>否</td><td>默认为 0</td></tr>
                <tr><td><code>sale_price</code></td><td>否</td><td>默认为 0</td></tr>
                <tr><td><code>quantity</code></td><td>否</td><td>期初库存，默认为 0</td></tr>
                <tr><td><code>reorder_level</code></td><td>否</td><td>默认为 0</td></tr>
                <?php foreach ($customFields as $key => $def): ?>
                    <tr><td><code><?= htmlspecialchars($def['label']) ?></code></td><td>否</td><td>自定义字段（也可使用列名 <code><?= htmlspecialchars($key) ?></code>)</td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="text-muted" style="margin-top:16px;">
            提示：请先使用 <a href="<?= Router::url('/products/export') ?>">导出CSV</a> first to get a correctly
            formatted file, then edit it and re-import.
        </p>
    </div>
</div>
