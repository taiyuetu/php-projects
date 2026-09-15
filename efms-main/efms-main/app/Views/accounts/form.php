<?php $editing = $account !== null; ?>
<div class="topbar">
    <h1><?= $editing ? 'Edit Account' : 'New Account' ?></h1>
</div>

<?php if (!empty(errors())): ?>
    <div class="error-box">
        <?php foreach (errors() as $field => $errs): ?>
            <?php foreach ($errs as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width:520px;">
    <form method="POST" action="<?= $editing ? '/accounts/' . e($account['id']) : '/accounts' ?>">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

        <div class="row">
            <div style="flex:1;">
                <label>Code</label>
                <input name="code" value="<?= e(old('code', $account['code'] ?? '')) ?>" required>
            </div>
            <div style="flex:2;">
                <label>Name</label>
                <input name="name" value="<?= e(old('name', $account['name'] ?? '')) ?>" required>
            </div>
        </div>
        <div class="row">
            <div style="flex:1;">
                <label>Type</label>
                <select name="type" required>
                    <?php $selectedType = old('type', $account['type'] ?? ''); ?>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                            <?= e(ucfirst($type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div style="flex:1;">
                <label>Description</label>
                <textarea name="description" rows="3"><?= e(old('description', $account['description'] ?? '')) ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn"><?= $editing ? 'Save Changes' : 'Create Account' ?></button>
        <a href="/accounts" class="btn secondary">Cancel</a>
    </form>
</div>
