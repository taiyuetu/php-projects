<?php
class User extends Model
{
    protected string $table = 'users';

    public function findByUsername(string $username)
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function createUser(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        return (int) $this->insert($data);
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function usersWithEmployeeInfo()
    {
        $sql = "SELECT u.*, e.first_name, e.last_name, e.email as employee_email
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                ORDER BY u.id DESC";
        return $this->query($sql)->fetchAll();
    }
}
