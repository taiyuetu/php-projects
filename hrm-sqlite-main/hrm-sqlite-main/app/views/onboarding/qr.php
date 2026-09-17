<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-qr-code text-primary me-2"></i>入职登记二维码</h4>
                    <div class="text-muted small">让新员工用手机扫码，即可自助填写入职登记表。</div>
                </div>
                <a href="<?php echo BASE_URL; ?>/onboarding/applications" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-inbox"></i> 查看申请
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo (int) $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="text-center mb-4">
                <div class="d-inline-block p-3 bg-white border rounded shadow-sm">
                    <div id="qr-code"></div>
                </div>
                <div class="mt-3">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control bg-light" id="apply-url" readonly value="<?php echo htmlspecialchars($applyUrl); ?>">
                        <button class="btn btn-outline-secondary" type="button" id="copy-url-btn">
                            <i class="bi bi-clipboard"></i> 复制
                        </button>
                    </div>
                </div>
            </div>

            <div class="alert alert-info small mb-4">
                <i class="bi bi-lightbulb me-1"></i>
                请确保员工手机与本系统处于同一网络（或该地址可被访问），否则扫码后无法打开表单。
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                <div class="text-muted small">
                    生成时间：<?php echo htmlspecialchars($invite['created_at']); ?>
                </div>
                <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/regenerateInvite" onsubmit="return confirm('重新生成后，旧二维码将立即失效，确定继续？');">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-arrow-clockwise"></i> 重新生成
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    var applyUrl = <?php echo json_encode($applyUrl); ?>;
    if (typeof QRCode !== 'undefined') {
        new QRCode(document.getElementById('qr-code'), {
            text: applyUrl,
            width: 240,
            height: 240,
            correctLevel: QRCode.CorrectLevel.M
        });
    } else {
        // CDN unavailable — still show the link so it can be typed/copied
        document.getElementById('qr-code').innerHTML =
            '<div class="text-muted small p-4">二维码库加载失败，<br>请直接复制下方链接发给新员工。</div>';
    }
    document.getElementById('copy-url-btn').addEventListener('click', function () {
        var input = document.getElementById('apply-url');
        input.select();
        input.setSelectionRange(0, 99999);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(function () {
                var btn = document.getElementById('copy-url-btn');
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2"></i> 已复制';
                setTimeout(function () { btn.innerHTML = old; }, 1500);
            });
        } else {
            document.execCommand('copy');
        }
    });
</script>
