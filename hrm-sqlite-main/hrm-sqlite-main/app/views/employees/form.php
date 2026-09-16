<form method="POST" class="card p-4">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">名字</label><input name="first_name" class="form-control" required value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">姓氏</label><input name="last_name" class="form-control" required value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">邮箱</label><input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">电话</label><input name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">部门</label><select name="department_id" class="form-select"><option value="">未分配</option><?php foreach ($departments as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo (($employee['department_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">职位</label><input name="designation" class="form-control" value="<?php echo htmlspecialchars($employee['designation'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">薪资</label><input type="number" step="0.01" min="0" name="salary" class="form-control" value="<?php echo htmlspecialchars($employee['salary'] ?? '0'); ?>"></div>
        <div class="col-md-4"><label class="form-label">入职日期</label><input type="date" name="hire_date" class="form-control" value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">状态</label><select name="status" class="form-select">
            <?php 
            $statusOptions = ['Active' => '在职', 'Inactive' => '离职/停用', 'Terminated' => '已终止'];
            foreach ($statusOptions as $val => $label): 
            ?>
                <option value="<?php echo $val; ?>" <?php echo (($employee['status'] ?? 'Active') === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">保存员工信息</button> <a class="btn btn-link text-secondary" href="<?php echo BASE_URL; ?>/employee">取消</a></div>
</form>
