<?php if (!empty($pagination) && ($pagination['last_page'] ?? 1) > 1): ?>
    <?php
    $currentPage = (int) $pagination['current_page'];
    $lastPage = (int) $pagination['last_page'];
    $query = $_GET;
    ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; font-size:13px;">
        <div class="muted">
            Showing page <?= $currentPage ?> of <?= $lastPage ?> (<?= (int) $pagination['total'] ?> total)
        </div>
        <div style="display:flex; gap:8px;">
            <?php if ($currentPage > 1): ?>
                <?php $query['page'] = $currentPage - 1; ?>
                <a href="?<?= http_build_query($query) ?>" class="btn secondary small">« Previous</a>
            <?php endif; ?>

            <?php if ($currentPage < $lastPage): ?>
                <?php $query['page'] = $currentPage + 1; ?>
                <a href="?<?= http_build_query($query) ?>" class="btn secondary small">Next »</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
