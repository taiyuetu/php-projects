<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Model
 *
 * Base Active Record class. To add a brand-new business object to the
 * system you only need a subclass like:
 *
 *   class Budget extends Model
 *   {
 *       protected static string $table = 'budgets';
 *       protected static array $fillable = ['name', 'account_id', 'amount', 'period'];
 *       protected static array $casts = ['amount' => 'float'];
 *   }
 *
 * That alone gives you Budget::find(), Budget::all(), Budget::query(),
 * Budget::create([...]), $budget->save(), $budget->delete(), and
 * relation helpers (belongsTo/hasMany). No boilerplate CRUD SQL
 * needed per-model — this is what keeps new modules fast to add.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $casts = [];
    protected static bool $timestamps = true;

    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ---- Table / connection plumbing -------------------------------------

    public static function table(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }

        // Fallback: derive snake_case plural table name from class name.
        $short = (new \ReflectionClass(static::class))->getShortName();
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

        return $snake . 's';
    }

    protected static function db(): Database
    {
        return Database::getInstance();
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::db(), static::table(), static::class);
    }

    // ---- Attribute handling -------------------------------------------

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (empty(static::$fillable) || in_array($key, static::$fillable, true) || $key === static::$primaryKey) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        if ($value !== null && isset(static::$casts[$key])) {
            $value = match (static::$casts[$key]) {
                'int', 'integer' => (int) $value,
                'float', 'decimal' => (float) $value,
                'bool', 'boolean' => (bool) $value,
                'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
                default => $value,
            };
        }

        return $value;
    }

    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function toArray(): array
    {
        $result = [];
        foreach (array_keys($this->attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }
        return $result;
    }

    // ---- Retrieval -------------------------------------------------------

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(int|string $id): ?array
    {
        return static::query()->where(static::$primaryKey, '=', $id)->first();
    }

    public static function findOrFail(int|string $id): array
    {
        $row = static::find($id);
        if ($row === null) {
            throw new \RuntimeException(static::class . " [{$id}] not found.");
        }
        return $row;
    }

    // ---- Persistence -------------------------------------------------

    /**
     * Creates and persists a new record in one call. This is the
     * primary write path used by controllers:
     *   Account::create($this->validate($request, [...]));
     */
    public static function create(array $attributes): array
    {
        $model = new static($attributes);
        $model->save();

        return $model->toArray();
    }

    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');

        if (static::$timestamps) {
            $this->attributes['updated_at'] = $now;
            $this->attributes['created_at'] ??= $now;
        }

        if (isset($this->attributes[static::$primaryKey]) && $this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    private function performInsert(): bool
    {
        $data = $this->attributes;
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::table(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        static::db()->query($sql, array_values($data));

        $id = static::db()->lastInsertId();
        if ($id !== '' && !isset($this->attributes[static::$primaryKey])) {
            $this->attributes[static::$primaryKey] = (int) $id;
        }
        $this->exists = true;

        return true;
    }

    private function performUpdate(): bool
    {
        $data = $this->attributes;
        $id = $data[static::$primaryKey];
        unset($data[static::$primaryKey]);

        $assignments = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s = ?', static::table(), $assignments, static::$primaryKey);

        $bindings = array_values($data);
        $bindings[] = $id;

        static::db()->query($sql, $bindings);

        return true;
    }

    /** Static convenience: Account::update($id, [...]) without loading the row. */
    public static function update(int|string $id, array $attributes): bool
    {
        if (static::$timestamps) {
            $attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $assignments = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($attributes)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s = ?', static::table(), $assignments, static::$primaryKey);

        $bindings = array_values($attributes);
        $bindings[] = $id;

        static::db()->query($sql, $bindings);

        return true;
    }

    public static function destroy(int|string $id): bool
    {
        $sql = sprintf('DELETE FROM %s WHERE %s = ?', static::table(), static::$primaryKey);
        static::db()->query($sql, [$id]);

        return true;
    }

    // ---- Relationships (return arrays, keep it simple/explicit) ------

    protected static function belongsTo(array $row, string $relatedClass, string $foreignKey): ?array
    {
        if (empty($row[$foreignKey])) {
            return null;
        }

        /** @var class-string<Model> $relatedClass */
        return $relatedClass::find($row[$foreignKey]);
    }

    protected static function hasMany(string $relatedClass, string $foreignKey, int|string $id): array
    {
        /** @var class-string<Model> $relatedClass */
        return $relatedClass::query()->where($foreignKey, '=', $id)->get();
    }
}
