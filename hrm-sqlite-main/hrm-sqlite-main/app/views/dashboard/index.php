<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-primary"><i class="bi bi-people"></i></div>
            <div>
                <div class="text-muted small">员工总数</div>
                <div class="fs-4 fw-bold"><?php echo $totalEmployees; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-success"><i class="bi bi-person-check"></i></div>
            <div>
                <div class="text-muted small">在职员工</div>
                <div class="fs-4 fw-bold"><?php echo $activeEmployees; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-info"><i class="bi bi-diagram-3"></i></div>
            <div>
                <div class="text-muted small">部门数量</div>
                <div class="fs-4 fw-bold"><?php echo $totalDepartments; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="text-muted small">待审批请假</div>
                <div class="fs-4 fw-bold"><?php echo $pendingLeaves; ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($_SESSION['user_role'] ?? '', ['admin', 'hr'])): ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="icon bg-primary text-white rounded-3 p-2 px-3 fs-4"><i class="bi bi-briefcase"></i></div>
                <div>
                    <div class="fw-semibold">招聘管理 (ATS)</div>
                    <div class="text-muted small"><?php echo (int)($openJobs ?? 0); ?> 个招聘职位 · <?php echo (int)($activeCandidates ?? 0); ?> 名活跃候选人</div>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-sm btn-outline-primary">招聘看板</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="icon bg-success text-white rounded-3 p-2 px-3 fs-4"><i class="bi bi-person-plus"></i></div>
                <div>
                    <div class="fw-semibold">入职管理</div>
                    <div class="text-muted small">入职清单与培训</div>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-sm btn-outline-success">入职管理</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="icon bg-warning text-dark rounded-3 p-2 px-3 fs-4"><i class="bi bi-box-arrow-right"></i></div>
                <div>
                    <div class="fw-semibold">离职管理</div>
                    <div class="text-muted small">离职交接与归档</div>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/offboarding" class="btn btn-sm btn-outline-warning">离职管理</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$statusCn = [
    'Present' => '出勤', 'Absent' => '缺勤', 'Late' => '迟到', 'Half Day' => '半天假', 'Leave' => '请假',
    'Active' => '在职', 'On Leave' => '休假', 'Terminated' => '已离职'
];
?>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card p-3">
            <h6 class="mb-3">今日考勤</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>员工姓名</th><th>签到时间</th><th>签退时间</th><th>状态</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todayAttendance)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">今日暂无考勤记录。</td></tr>
                        <?php else: foreach ($todayAttendance as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                                <td><?php echo $a['check_in'] ?? '—'; ?></td>
                                <td><?php echo $a['check_out'] ?? '—'; ?></td>
                                <td><span class="badge badge-status-<?php echo str_replace(' ', '.', $a['status']); ?>"><?php echo $statusCn[$a['status']] ?? $a['status']; ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card p-3">
            <h6 class="mb-3">最新加入员工</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentEmployees as $e): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($e['designation'] ?? ''); ?> · <?php echo htmlspecialchars($e['department_name'] ?? '未分配'); ?></div>
                        </div>
                        <span class="badge badge-status-<?php echo $e['status']; ?>"><?php echo $statusCn[$e['status']] ?? $e['status']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
