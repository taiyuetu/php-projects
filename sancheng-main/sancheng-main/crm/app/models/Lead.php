<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Lead extends Model
{
    /** 线索编号：LEAD-000007（自动生成，供 AI 与人工稳定引用） */
    protected ?string $publicCodePrefix = 'LEAD';

    protected string $table = 'leads';

    /**
     * 字段语义注册表（稀疏）：结构看 schema.sql，这里只补语义。
     * searchable 列与改动前 $searchable 的清单一致 → 搜索行为不变。
     */
    protected static array $fields = [
        'title'       => ['label' => '线索标题', 'type' => 'string', 'searchable' => true,
                          'required' => true, 'requiredMsg' => '线索标题不能为空。'],
        'company'     => ['label' => '公司', 'searchable' => true],
        'contact_name'    => ['label' => '联系人', 'searchable' => true],
        'contact_email'   => ['label' => '联系邮箱', 'type' => 'email', 'searchable' => true],
        'lead_time'   => ['label' => '线索时间', 'type' => 'datetime'],
        'whatsapp'    => ['label' => 'WhatsApp', 'searchable' => true],
        'wechat'      => ['label' => '微信', 'searchable' => true],
        'phone'       => ['label' => '电话', 'searchable' => true],
        'facebook'    => ['label' => 'Facebook 主页'],
        'tiktok'      => ['label' => 'TikTok 频道'],
        'website'     => ['label' => '官方网站'],
        'source'      => ['label' => '来源', 'searchable' => true],
        'source_category_id' => ['label' => '线索来源分类', 'type' => 'int',
                                 // 表单已在 _form.php 手写（带类型化的下拉），不标 'form' 以免自动区重复渲染
                                 'hint' => '在「线索 → 线索来源分类」里维护。'],
        'grade'       => ['label' => '线索星级', 'type' => 'enum',
                          'options' => ['A', 'B', 'C', 'D'], 'strict' => true],
        'source_country' => ['label' => '来源国家', 'searchable' => true],
        'source_city'    => ['label' => '来源城市', 'searchable' => true],
        'address'     => ['label' => '地址'],
        'status'      => ['label' => '状态', 'type' => 'enum', 'default' => 'new'],
        'lost_reason' => ['label' => '流失原因', 'type' => 'enum', 'writable' => false],
        'value'       => ['label' => '预估金额', 'type' => 'number', 'default' => '0'],
        'first_purchase_from_china' => ['label' => '是否首次从中国采购', 'type' => 'bool'],
        'has_import_capability'     => ['label' => '是否有进口能力', 'type' => 'bool'],
        'notes'       => ['label' => '备注', 'type' => 'text', 'searchable' => true],
    ];

    /**
     * All leads, newest first. $status = 状态精确筛选，$search = 跨列关键词，
     * $grade = 星级筛选（A/B/C/D），$sourceCategoryId = 来源分类筛选。
     * （$search 放在参数末位：老调用 allLeads($status,$page,$perPage) 不受影响）
     */
    public function allLeads(string $status = '', int $page = 1, int $perPage = 15, string $search = '',
                             string $grade = '', int $sourceCategoryId = 0): array
    {
        $sql = "SELECT l.*, u.name AS owner_name, sc.name AS source_category_name
                FROM leads l
                LEFT JOIN users u ON u.id = l.owner_id
                LEFT JOIN categories sc ON sc.id = l.source_category_id";
        $params = [];

        $where = [];
        if ($status !== '') {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        }
        if ($grade !== '') {
            $where[] = 'l.grade = :grade';
            $params[':grade'] = $grade;
        }
        if ($sourceCategoryId > 0) {
            $where[] = 'l.source_category_id = :source_category_id';
            $params[':source_category_id'] = $sourceCategoryId;
        }
        if ($search !== '') {
            [$bits, $sparams] = $this->searchWhere($search, 'l');
            $where[] = $bits;
            $params = array_merge($params, $sparams);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        $stmt->bind(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bind(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        return $stmt->resultSet();
    }

    /** Count leads matching optional status/grade/source-category filter and/or keyword. */
    public function countLeads(string $status = '', string $search = '', string $grade = '', int $sourceCategoryId = 0): int
    {
        $sql = "SELECT COUNT(*) AS total FROM leads l";
        $params = [];

        $where = [];
        if ($status !== '') {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        }
        if ($grade !== '') {
            $where[] = 'l.grade = :grade';
            $params[':grade'] = $grade;
        }
        if ($sourceCategoryId > 0) {
            $where[] = 'l.source_category_id = :source_category_id';
            $params[':source_category_id'] = $sourceCategoryId;
        }
        if ($search !== '') {
            [$bits, $sparams] = $this->searchWhere($search, 'l');
            $where[] = $bits;
            $params = array_merge($params, $sparams);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return (int) ($stmt->single()['total'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        return $this->count('status = :status', [':status' => $status]);
    }

    /** Mark lead as lost with reason */
    public function markAsLost(int $id, string $reason): bool
    {
        return $this->update($id, [
            'status' => 'lost',
            'lost_reason' => $reason,
            'lost_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Reactivate a lost lead back to contacted status */
    public function reactivate(int $id): bool
    {
        return $this->update($id, [
            'status' => 'contacted',
            'lost_reason' => null,
            'lost_at' => null,
        ]);
    }

    /** Get lost reason options */
    public static function lostReasonOptions(): array
    {
        return [
            'no_need' => '暂无需求',
            'competitor' => '已选竞品',
            'budget' => '预算不足',
            'no_match' => '需求不匹配',
            'no_response' => '长期无响应',
            'project_cancel' => '项目取消',
            'contact_lost' => '联系不上',
            'other' => '其他原因',
        ];
    }

    /** Get lost reason label */
    public static function lostReasonLabel(string $reason): string
    {
        $options = self::lostReasonOptions();
        return $options[$reason] ?? '未知原因';
    }

    /** 星级可选项：value => 短标签 */
    public static function gradeOptions(): array
    {
        return [
            'A' => 'A 类 · 热线索',
            'B' => 'B 类 · 温线索',
            'C' => 'C 类 · 冷线索',
            'D' => 'D 类 · 无效线索',
        ];
    }

    /** 星级判定标准（表单提示/列表说明用） */
    public static function gradeDescriptions(): array
    {
        return [
            'A' => '明确需求 + 预算 + 近期要采购，可直接转商机',
            'B' => '有需求，预算待定，1–3 个月内考虑，持续跟进培育',
            'C' => '潜在需求，暂无采购计划，长期培育',
            'D' => '信息错误、非目标客户、拒绝沟通，归档 / 放弃',
        ];
    }

    /** 星级完整标签：A 类（热线索） */
    public static function gradeLabel(string $grade): string
    {
        $names = ['A' => '热线索', 'B' => '温线索', 'C' => '冷线索', 'D' => '无效线索'];
        return isset($names[$grade]) ? $grade . ' 类（' . $names[$grade] . '）' : $grade;
    }

    /** 星级徽章配色（Bootstrap 语义色） */
    public static function gradeBadgeClass(string $grade): string
    {
        return [
            'A' => 'bg-danger-subtle text-danger',
            'B' => 'bg-warning-subtle text-warning-emphasis',
            'C' => 'bg-info-subtle text-info',
            'D' => 'bg-secondary-subtle text-secondary',
        ][$grade] ?? 'bg-light text-muted';
    }
}
