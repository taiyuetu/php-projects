<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class FollowUp extends Model
{
    protected string $table = 'follow_ups';

    /** 跟进类型：取值与 schema.sql 的 CHECK(type IN …) 一致；中文名一处声明、表单与徽章共用 */
    public const TYPES = [
        'price_comparison' => '比价询价',
        'no_response'      => '无回复',
        'follow_up'        => '跟进中',
        'other'            => '其他',
    ];

    /**
     * 字段语义注册表（稀疏）：结构看 schema.sql，这里只补"中文名 + 校验规则"。
     *
     * 故意**不**登记进 Fields::REGISTERED：那会让 follow_ups 的关键词搜索列从
     * 自动推断切到显式声明，也会改掉 AI 侧的参数文案；本表目前只需要页面表单的
     * 校验，所以注册表只给 Controller 的 sanitizeInput() 用（Fields::sanitize 会
     * 直接读 Schema 的 CHECK 拿到枚举可选值，不依赖 REGISTERED）。
     */
    protected static array $fields = [
        'type'        => ['label' => '类型', 'type' => 'enum', 'strict' => true, 'default' => 'price_comparison'],
        'title'       => ['label' => '标题', 'required' => true, 'requiredMsg' => '跟进标题不能为空。'],
        'description' => ['label' => '描述', 'type' => 'text'],
        'next_action' => ['label' => '下一步行动'],
        'next_date'   => ['label' => '下次跟进日期', 'type' => 'date'],
    ];

    /** for <select>：value => 中文名 */
    public static function typeOptions(): array
    {
        return self::TYPES;
    }

    /** 类型的中文名；库里的未知取值不装懂，直接说"未知" */
    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type] ?? '未知';
    }

    /** 类型徽章的配色（取值/文案仍来自 TYPES，视图只挑颜色） */
    public static function typeBadgeClass(?string $type): string
    {
        return [
            'price_comparison' => 'bg-warning text-dark',
            'no_response'      => 'bg-secondary',
            'follow_up'        => 'bg-info',
            'other'            => 'bg-light text-dark',
        ][$type] ?? 'bg-light text-dark';
    }

    /** Get all follow-ups for a customer */
    public function byCustomer(int $customerId): array
    {
        return $this->db()->query(
            "SELECT f.*, u.name AS user_name
             FROM follow_ups f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.customer_id = :id
             ORDER BY f.created_at DESC"
        )->bind(':id', $customerId)->resultSet();
    }

    /**
     * 一条属于该客户的跟进记录；不存在或不属于该客户都返回 null。
     * 改/删前用它做归属校验——URL 上的客户 ID 是用户可控的，不能只信跟进 ID。
     */
    public function findForCustomer(int $customerId, int $followUpId): ?array
    {
        $row = $this->db()->query(
            "SELECT * FROM follow_ups WHERE id = :id AND customer_id = :cid"
        )->bind(':id', $followUpId)->bind(':cid', $customerId)->single();

        return $row ?: null;
    }

    /** Add a new follow-up record */
    public function addFollowUp(int $customerId, ?int $userId, array $data): int
    {
        return $this->db()->query(
            "INSERT INTO follow_ups (customer_id, user_id, type, title, description, next_action, next_date)
             VALUES (:cid, :uid, :type, :title, :desc, :next_action, :next_date)"
        )->bind(':cid', $customerId)
         ->bind(':uid', $userId)
         ->bind(':type', $data['type'] ?? 'price_comparison')
         ->bind(':title', $data['title'])
         ->bind(':desc', $data['description'] ?? null)
         ->bind(':next_action', $data['next_action'] ?? null)
         ->bind(':next_date', $data['next_date'] ?: null)
         ->execute() ? (int) $this->db()->lastInsertId() : 0;
    }
}
