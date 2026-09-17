<div class="card shadow-sm">
    <div class="card-body p-4">
        <h4 class="mb-1"><i class="bi bi-person-plus text-primary me-2"></i>新员工入职登记</h4>
        <p class="text-muted small mb-4">请如实填写以下信息，提交后 HR 将尽快审核并安排入职事宜。带 <span class="text-danger">*</span> 为必填项。</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>请修正以下问题后重新提交：</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>/apply/form/<?php echo urlencode($invite['token']); ?>">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <!-- 1. 基本信息 -->
            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">1. 基本信息</h6>
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <label class="form-label fw-semibold">姓氏 <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($old['last_name']); ?>" placeholder="例如：张" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">名字 <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($old['first_name']); ?>" placeholder="例如：三" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">电子邮箱 <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($old['email']); ?>" placeholder="例如：zhangsan@example.com" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">联系电话 <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($old['phone']); ?>" placeholder="例如：13800138000" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">性别</label>
                    <select name="gender" class="form-select">
                        <option value="">-- 选择 --</option>
                        <option value="Male" <?php echo $old['gender'] === 'Male' ? 'selected' : ''; ?>>男</option>
                        <option value="Female" <?php echo $old['gender'] === 'Female' ? 'selected' : ''; ?>>女</option>
                        <option value="Other" <?php echo $old['gender'] === 'Other' ? 'selected' : ''; ?>>其他</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">出生日期</label>
                    <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($old['dob']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">身份证号</label>
                    <input type="text" name="id_number" class="form-control" value="<?php echo htmlspecialchars($old['id_number']); ?>" placeholder="身份证号码">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">民族</label>
                    <input type="text" name="ethnicity" class="form-control" value="<?php echo htmlspecialchars($old['ethnicity']); ?>" placeholder="例如：汉族">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">学历</label>
                    <select name="education" class="form-select">
                        <option value="">-- 选择 --</option>
                        <?php foreach (['高中', '中专', '大专', '本科', '硕士', '博士', '其他'] as $edu): ?>
                            <option value="<?php echo $edu; ?>" <?php echo $old['education'] === $edu ? 'selected' : ''; ?>><?php echo $edu; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 2. 地址信息 -->
            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">2. 地址信息</h6>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label class="form-label fw-semibold">户籍地址</label>
                    <input type="text" name="household_address" class="form-control" value="<?php echo htmlspecialchars($old['household_address']); ?>" placeholder="户籍所在地">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">现居住址</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($old['address']); ?>" placeholder="街道地址、门牌号">
                </div>
            </div>

            <!-- 3. 紧急联系人 -->
            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">3. 紧急联系人</h6>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label class="form-label fw-semibold">紧急联系人姓名</label>
                    <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($old['emergency_contact']); ?>" placeholder="例如：张四">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">与本人关系</label>
                    <input type="text" name="emergency_relation" class="form-control" value="<?php echo htmlspecialchars($old['emergency_relation']); ?>" placeholder="例如：父亲">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">紧急联系人电话</label>
                    <input type="tel" name="emergency_phone" class="form-control" value="<?php echo htmlspecialchars($old['emergency_phone']); ?>" placeholder="联系电话">
                </div>
            </div>

            <!-- 4. 职位信息 -->
            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">4. 职位信息</h6>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label class="form-label fw-semibold">意向部门</label>
                    <select name="department_id" class="form-select">
                        <option value="">-- 选择部门 --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo (string) $old['department_id'] === (string) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">意向职位</label>
                    <input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($old['designation']); ?>" placeholder="例如：软件工程师">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">期望薪资（月薪）</label>
                    <input type="number" step="0.01" min="0" name="expected_salary" class="form-control" value="<?php echo htmlspecialchars($old['expected_salary']); ?>" placeholder="例如：8000">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">备注</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="其他需要说明的信息"><?php echo htmlspecialchars($old['notes']); ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-send me-1"></i> 提交入职申请
            </button>
        </form>
    </div>
</div>
