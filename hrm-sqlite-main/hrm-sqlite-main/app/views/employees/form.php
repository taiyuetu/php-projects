<form method="POST" action="<?php echo BASE_URL; ?>/employee/<?php echo isset($employee['id']) ? 'edit/' . $employee['id'] : 'create'; ?>" class="card p-4">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">姓氏</label><input name="last_name" class="form-control" required value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">名字</label><input name="first_name" class="form-control" required value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">邮箱</label><input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">电话</label><input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">身份证号</label><input type="text" name="id_number" class="form-control" placeholder="身份证号码" value="<?php echo htmlspecialchars($employee['id_number'] ?? ''); ?>"></div>
        <div class="col-md-3"><label class="form-label">性别</label><select name="gender" class="form-select"><option value="">请选择</option><option value="Male" <?php echo (($employee['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>男</option><option value="Female" <?php echo (($employee['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>女</option><option value="Other" <?php echo (($employee['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>其他</option></select></div>
        <div class="col-md-3"><label class="form-label">民族</label><input type="text" name="ethnicity" class="form-control" placeholder="例如：汉族" value="<?php echo htmlspecialchars($employee['ethnicity'] ?? ''); ?>"></div>
        <div class="col-md-3"><label class="form-label">学历</label><select name="education" class="form-select"><option value="">请选择</option><?php foreach (['初中及以下' => '初中及以下', '高中/中专' => '高中/中专', '大专' => '大专', '本科' => '本科', '硕士' => '硕士', '博士' => '博士'] as $val => $label): ?><option value="<?php echo $val; ?>" <?php echo (($employee['education'] ?? '') === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">出生日期</label><input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($employee['dob'] ?? ''); ?>" onchange="calculateAge(this.value)"></div>
        <div class="col-md-6"><label class="form-label">年龄</label><input type="text" id="age" class="form-control bg-light" readonly placeholder="自动计算" value="<?php echo isset($employee['dob']) && !empty($employee['dob']) ? htmlspecialchars($employeeModel->calculateAge($employee['dob'])) : ''; ?>"></div>
        <div class="col-12"><label class="form-label">现居住地址</label><textarea name="address" class="form-control" rows="2" placeholder="请输入详细地址"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea></div>
        <div class="col-12"><label class="form-label">户籍地址</label><textarea name="household_address" class="form-control" rows="2" placeholder="户口所在地详细地址"><?php echo htmlspecialchars($employee['household_address'] ?? ''); ?></textarea></div>
    </div>

    <hr class="my-4">

    <h6 class="fw-bold">紧急联系人</h6>
    <div class="row g-3 mt-2">
        <div class="col-md-4"><label class="form-label">姓名</label><input type="text" name="emergency_contact" class="form-control" placeholder="紧急联系人姓名" value="<?php echo htmlspecialchars($employee['emergency_contact'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">关系</label><select name="emergency_relation" class="form-select"><option value="">请选择</option><?php foreach (['父母' => '父母', '配偶' => '配偶', '子女' => '子女', '兄弟姐妹' => '兄弟姐妹', '朋友' => '朋友', '其他' => '其他'] as $val => $label): ?><option value="<?php echo $val; ?>" <?php echo (($employee['emergency_relation'] ?? '') === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">联系电话</label><input type="tel" name="emergency_phone" class="form-control" placeholder="紧急联系人电话" value="<?php echo htmlspecialchars($employee['emergency_phone'] ?? ''); ?>"></div>
    </div>

    <hr class="my-4">

    <h6 class="fw-bold">工作信息</h6>
    <div class="row g-3 mt-2">
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

<script>
function calculateAge(birthDate) {
    if (!birthDate) {
        document.getElementById('age').value = '';
        return;
    }
    const birth = new Date(birthDate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    document.getElementById('age').value = age > 0 ? age : '';
}
</script>
