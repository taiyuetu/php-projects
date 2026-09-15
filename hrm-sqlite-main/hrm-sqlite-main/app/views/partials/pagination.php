<?php
if (!isset($pagination) || !is_array($pagination)) {
    return;
}

$pageParam = $pagination['page_param'] ?? 'page';
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$totalItems = (int) ($pagination['total_items'] ?? 0);
$from = (int) ($pagination['from'] ?? 0);
$to = (int) ($pagination['to'] ?? 0);

// Build helper to generate URL for given page
$queryParams = $_GET;
unset($queryParams['url']);

$getPageUrl = function(int $page) use ($queryParams, $pageParam) {
    $params = $queryParams;
    $params[$pageParam] = $page;
    return '?' . http_build_query($params);
};

$startPage = max(1, $currentPage - 2);
$endPage = min($totalPages, $currentPage + 2);
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
    <div class="text-muted small">
        Showing <span class="fw-semibold"><?php echo $from; ?></span> to <span class="fw-semibold"><?php echo $to; ?></span> of <span class="fw-semibold"><?php echo $totalItems; ?></span> entries
    </div>
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $currentPage > 1 ? htmlspecialchars($getPageUrl($currentPage - 1)) : '#'; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo; Prev</span>
                    </a>
                </li>

                <?php if ($startPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo htmlspecialchars($getPageUrl(1)); ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars($getPageUrl($p)); ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo htmlspecialchars($getPageUrl($totalPages)); ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php endif; ?>

                <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $currentPage < $totalPages ? htmlspecialchars($getPageUrl($currentPage + 1)) : '#'; ?>" aria-label="Next">
                        <span aria-hidden="true">Next &raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
