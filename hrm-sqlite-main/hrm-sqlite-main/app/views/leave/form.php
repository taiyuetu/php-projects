<form method="POST" class="card p-4" style="max-width:700px">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
    <label class="form-label">请假类型</label>
    <select name="leave_type" class="form-select mb-3">
        <?php 
        $types = ['Annual' => '年假', 'Sick' => '病假', 'Casual' => '事假', 'Unpaid' => '无薪假', 'Other' => '其他假'];
        foreach ($types as $val => $label): 
        ?>
            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
    <div class="row">
        <div class="col"><label class="form-label">开始日期</label><input type="date" name="start_date" class="form-control" required></div>
        <div class="col"><label class="form-label">结束日期</label><input type="date" name="end_date" class="form-control" required></div>
    </div>
    <label class="form-label mt-3">请假原因</label>
    <textarea name="reason" class="form-control mb-3" rows="3"></textarea>
    <button class="btn btn-primary">提交申请</button>
</form>
