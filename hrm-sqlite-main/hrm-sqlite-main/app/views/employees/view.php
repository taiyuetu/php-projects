<?php
$genderLabels = ['Male' => '男', 'Female' => '女', 'Other' => '其他'];
$statusLabels = ['Active' => '在职', 'Inactive' => '停用', 'Terminated' => '已离职'];
$age = $employeeModel->calculateAge($employee['dob'] ?? null);
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-badge me-2"></i>员工档案</h4>
        <div class="text-muted small">查看员工详细信息和历史记录</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/employee/edit/<?php echo $employee['id']; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> 编辑
        </a>
        <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回列表
        </a>
    </div>
</div>

<!-- Employee Info Card -->
<div class="row g-4">
    <!-- Left Column: Profile Card -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="bi bi-person-fill text-primary" style="font-size: 3rem;"></i>
                    </div>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($employee['last_name'] . $employee['first_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($employee['designation'] ?? '未设置职位'); ?></p>
                <span class="badge bg-<?php echo $employee['status'] === 'Active' ? 'success' : 'secondary'; ?> fs-6">
                    <?php echo $statusLabels[$employee['status']] ?? $employee['status']; ?>
                </span>
            </div>
            <div class="card-footer bg-light">
                <div class="text-center">
                    <span class="badge bg-light text-dark border">工号：<?php echo htmlspecialchars($employee['employee_code']); ?></span>
                </div>
            </div>
        </div>

        <!-- Contact Info Card -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>联系方式</h6>
            </div>
            <div class="card-body py-2">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-envelope text-muted me-2" style="width: 20px;"></i>
                    <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($employee['email']); ?>
                    </a>
                </div>
                <?php if (!empty($employee['phone'])): ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-telephone text-muted me-2" style="width: 20px;"></i>
                    <a href="tel:<?php echo htmlspecialchars($employee['phone']); ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($employee['phone']); ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($employee['address'])): ?>
                <div class="d-flex align-items-start">
                    <i class="bi bi-geo-alt text-muted me-2" style="width: 20px;"></i>
                    <small><?php echo htmlspecialchars($employee['address']); ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Details -->
    <div class="col-md-8">
        <!-- Personal Information -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i>个人信息</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">姓名</small>
                            <div class="fw-semibold"><?php echo htmlspecialchars($employee['last_name'] . $employee['first_name']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">性别</small>
                            <div><?php echo $genderLabels[$employee['gender'] ?? ''] ?? '—'; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">民族</small>
                            <div><?php echo htmlspecialchars($employee['ethnicity'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">学历</small>
                            <div><?php echo htmlspecialchars($employee['education'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">身份证号</small>
                            <div><?php echo htmlspecialchars($employee['id_number'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">出生日期</small>
                            <div>
                                <?php echo htmlspecialchars($employee['dob'] ?? '—'); ?>
                                <?php if ($age): ?>
                                    <span class="badge bg-info ms-2"><?php echo $age; ?>岁</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">现居住地址</small>
                            <div><?php echo htmlspecialchars($employee['address'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">户籍地址</small>
                            <div><?php echo htmlspecialchars($employee['household_address'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="mb-2">
                            <small class="text-muted">紧急联系人</small>
                            <div>
                                <?php if (!empty($employee['emergency_contact'])): ?>
                                    <?php echo htmlspecialchars($employee['emergency_contact']); ?>
                                    <?php if (!empty($employee['emergency_relation'])): ?>
                                        <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($employee['emergency_relation']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($employee['emergency_phone'])): ?>
                                        · <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($employee['emergency_phone']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Work Information -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-briefcase me-2"></i>工作信息</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">工号</small>
                            <div><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($employee['employee_code']); ?></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">部门</small>
                            <div><?php echo htmlspecialchars($employee['department_name'] ?? '未分配'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">职位</small>
                            <div><?php echo htmlspecialchars($employee['designation'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">薪资</small>
                            <div class="text-success fw-semibold">￥<?php echo number_format($employee['salary'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">入职日期</small>
                            <div><?php echo htmlspecialchars($employee['hire_date'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <small class="text-muted">状态</small>
                            <div>
                                <span class="badge bg-<?php echo $employee['status'] === 'Active' ? 'success' : ($employee['status'] === 'Terminated' ? 'danger' : 'secondary'); ?>">
                                    <?php echo $statusLabels[$employee['status']] ?? $employee['status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($employee['created_at'])): ?>
                    <div class="col-12">
                        <div class="mb-2">
                            <small class="text-muted">档案创建时间</small>
                            <div><?php echo date('Y-m-d H:i', strtotime($employee['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>快捷操作</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <a href="<?php echo BASE_URL; ?>/employee/edit/<?php echo $employee['id']; ?>" class="btn btn-outline-primary w-100">
                            <i class="bi bi-pencil me-1"></i> 编辑资料
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?php echo BASE_URL; ?>/offboarding/initiate/<?php echo $employee['id']; ?>" class="btn btn-outline-warning w-100">
                            <i class="bi bi-box-arrow-right me-1"></i> 办理离职
                        </a>
                    </div>
                    <?php if (!empty($employee['email'])): ?>
                    <div class="col-md-4">
                        <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>" class="btn btn-outline-info w-100">
                            <i class="bi bi-envelope me-1"></i> 发送邮件
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Attendance Summary -->
        <?php if (!empty($attendance)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>考勤记录</h6>
                <a href="<?php echo BASE_URL; ?>/attendance" class="btn btn-sm btn-outline-secondary">查看全部</a>
            </div>
            <div class="card-body py-2">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>签到</th>
                                <th>签退</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $attendanceSlice = array_slice($attendance, 0, 5); ?>
                            <?php if (empty($attendanceSlice)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">暂无考勤记录</td></tr>
                            <?php else: foreach ($attendanceSlice as $att): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($att['attendance_date']); ?></td>
                                    <td><?php echo htmlspecialchars($att['check_in'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($att['check_out'] ?? '—'); ?></td>
                                    <td>
                                        <?php 
                                        $attStatusMap = ['Present' => '出勤', 'Absent' => '缺勤', 'Late' => '迟到', 'Half Day' => '半天假', 'Leave' => '请假'];
                                        echo $attStatusMap[$att['status']] ?? $att['status'];
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Leave Requests Summary -->
        <?php if (!empty($leaves)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>请假记录</h6>
                <a href="<?php echo BASE_URL; ?>/leave" class="btn btn-sm btn-outline-secondary">查看全部</a>
            </div>
            <div class="card-body py-2">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>类型</th>
                                <th>起止日期</th>
                                <th>原因</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $leavesSlice = array_slice($leaves, 0, 5); ?>
                            <?php if (empty($leavesSlice)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">暂无请假记录</td></tr>
                            <?php else: foreach ($leavesSlice as $leave): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $leaveTypeMap = ['Annual' => '年假', 'Sick' => '病假', 'Casual' => '事假', 'Unpaid' => '无薪假', 'Other' => '其他'];
                                        echo $leaveTypeMap[$leave['leave_type']] ?? $leave['leave_type'];
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($leave['start_date']); ?> 至 <?php echo htmlspecialchars($leave['end_date']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['reason'] ?? '—'); ?></td>
                                    <td>
                                        <?php 
                                        $leaveStatusMap = ['Pending' => '待审批', 'Approved' => '已批准', 'Rejected' => '已拒绝'];
                                        $statusClass = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'];
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass[$leave['status']] ?? 'secondary'; ?>">
                                            <?php echo $leaveStatusMap[$leave['status']] ?? $leave['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
