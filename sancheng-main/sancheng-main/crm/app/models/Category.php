<?php

/**
 * 分类主数据模型（商品分类 + 线索来源分类）
 *
 * categories.type 区分两类主数据：
 *   - product      商品分类（挂在商品 category_id 上）
 *   - lead_source  线索来源分类（挂在线索 source_category_id 上）
 *
 * 支持排序和状态控制。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class Category extends Model
{
    protected string $table = 'categories';

    /** 分类类型：标识 => 中文名 */
    public const TYPES = [
        'product'     => '商品分类',
        'lead_source' => '线索来源分类',
    ];

    /**
     * 字段语义注册表
     */
    protected static array $fields = [
        'name'       => ['label' => '分类名称', 'type' => 'string', 'searchable' => true,
                         'required' => true, 'requiredMsg' => '分类名称不能为空。', 'max' => 60,
                         'unique' => true],
        'type'       => ['label' => '分类类型', 'type' => 'enum', 'default' => 'product'],
        'sort_order' => ['label' => '排序', 'type' => 'int', 'default' => 0,
                         'form' => ['hint' => '数字越小越靠前，默认为 0。']],
        'status'     => ['label' => '状态', 'type' => 'enum', 'default' => 'active'],
    ];

    /** 分类名称唯一性检查 */
    protected function fieldUniqueTaken(string $field, string $value, int $selfId): bool
    {
        if ($field !== 'name') {
            return false;
        }
        $row = $this->db()->query('SELECT id FROM categories WHERE name = :n AND id <> :id LIMIT 1')
            ->bind(':n', trim($value))
            ->bind(':id', $selfId, PDO::PARAM_INT)
            ->single();
        return (bool) $row;
    }

    /**
     * sort_order 是真正的整数列，0 是合法值（默认排序）。
     * 但通用清洗把 int 的 0 当成“不关联的外键”置成 NULL（为 deal_id 等设计），
     * 这里把空值/0 统一归零，避免撞上 NOT NULL 约束。
     */
    public function sanitizeInput(array $input, array $ctx = []): array
    {
        [$data, $errors] = parent::sanitizeInput($input, $ctx);
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }
        return [$data, $errors];
    }

    public static function statusOptions(): array
    {
        return ['active' => '启用', 'inactive' => '停用'];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusOptions()[$status] ?? $status;
    }

    /** 类型中文名 */
    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    /** 合法类型白名单（表单/AI 传参防呆） */
    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * 所有启用的分类（用于下拉选择），按类型、排序字段、名称排序。
     * 静态方法里拿不到 Model 实例的 $db 包装，直接用 PDO。
     */
    public static function activeOptions(string $type = 'product'): array
    {
        static $cache = [];                    // 每请求只查一次（表单/筛选都会反复调用）
        $type = self::isValidType($type) ? $type : 'product';
        if (isset($cache[$type])) {
            return $cache[$type];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT id, name FROM categories WHERE status = 'active' AND type = :type ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute([':type' => $type]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $cache[$type] = $out;
    }

    /**
     * 下拉候选（activeOptions 基础上，保证当前值也在选项里）。
     * 正在编辑的商品/线索如果挂在已停用的分类下，下拉也要能看到、不能悄悄变成空选。
     */
    public static function optionsFor(?int $keepId, string $keepLabel = '', string $type = 'product'): array
    {
        $options = self::activeOptions($type);
        $typeLabel = self::typeLabel($type);
        if ($keepId !== null && $keepId > 0 && !array_key_exists($keepId, $options)) {
            $options = [$keepId => ($keepLabel !== '' ? $keepLabel : $typeLabel . '#' . $keepId) . '（已停用）'] + $options;
        }
        return $options;
    }

    /**
     * 所有分类（带关联记录数量），用于管理页面。
     * 商品分类统计商品数，线索来源分类统计线索数。
     */
    public function allWithCount(string $type = 'product'): array
    {
        $type = self::isValidType($type) ? $type : 'product';
        if ($type === 'lead_source') {
            return $this->db()->query(
                'SELECT c.*, COUNT(l.id) AS usage_count
                 FROM categories c
                 LEFT JOIN leads l ON l.source_category_id = c.id
                 WHERE c.type = :type
                 GROUP BY c.id
                 ORDER BY c.sort_order ASC, c.name ASC'
            )->bind(':type', $type)->resultSet();
        }
        return $this->db()->query(
            'SELECT c.*, COUNT(p.id) AS usage_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             WHERE c.type = :type
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        )->bind(':type', $type)->resultSet();
    }

    /** 某个商品分类下有多少商品 */
    public function productCount(int $id): int
    {
        $row = $this->db()->query('SELECT COUNT(*) AS c FROM products WHERE category_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)
            ->single();
        return (int) ($row['c'] ?? 0);
    }

    /** 某个线索来源分类下有多少线索 */
    public function leadSourceCount(int $id): int
    {
        $row = $this->db()->query('SELECT COUNT(*) AS c FROM leads WHERE source_category_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)
            ->single();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * 删除分类时，将关联的商品/线索的分类引用设为 NULL
     */
    public function delete(int $id): bool
    {
        // 先解除关联
        $this->db()->query('UPDATE products SET category_id = NULL WHERE category_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)
            ->execute();
        $this->db()->query('UPDATE leads SET source_category_id = NULL WHERE source_category_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)
            ->execute();

        // 再删除分类
        return parent::delete($id);
    }
}
