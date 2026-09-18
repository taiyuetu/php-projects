<?php
/**
 * Expects $deleteUrl and $this (controller) in scope for csrfField().
 * Usage: $deleteUrl = Router::url('/categories/5/delete'); include partial.
 */
use App\Core\Router;
?>
<form method="post" action="<?= $deleteUrl ?>" onsubmit="return confirm('确定要删除吗？此操作无法撤销。');" style="display:inline;">
    <?= $this->csrfField() ?>
    <button type="submit" class="btn btn-danger btn-sm">删除</button>
</form>
