<form method="POST" class="card p-4">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">First name</label><input name="first_name" class="form-control" required value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">Last name</label><input name="last_name" class="form-control" required value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Unassigned</option><?php foreach ($departments as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo (($employee['department_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Designation</label><input name="designation" class="form-control" value="<?php echo htmlspecialchars($employee['designation'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">Salary</label><input type="number" step="0.01" min="0" name="salary" class="form-control" value="<?php echo htmlspecialchars($employee['salary'] ?? '0'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Hire date</label><input type="date" name="hire_date" class="form-control" value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>"></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['Active','Inactive','Terminated'] as $status): ?><option <?php echo (($employee['status'] ?? 'Active') === $status) ? 'selected' : ''; ?>><?php echo $status; ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save employee</button> <a class="btn btn-link" href="<?php echo BASE_URL; ?>/employee">Cancel</a></div>
</form>
