<?php
/**
 * Onboarding applications submitted by new hires through the QR-code form.
 */
class OnboardingApplication extends Model
{
    protected string $table = 'onboarding_applications';

    public function allWithDetails(string $status = ''): array
    {
        $sql = "SELECT a.*, d.name AS department_name
                FROM onboarding_applications a
                LEFT JOIN departments d ON a.department_id = d.id";
        $params = [];
        if ($status !== '') {
            $sql .= " WHERE a.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY CASE a.status WHEN 'Pending' THEN 1 WHEN 'Approved' THEN 2 ELSE 3 END, a.id DESC";
        return $this->query($sql, $params)->fetchAll();
    }

    public function findWithDepartment(int $id): ?array
    {
        $sql = "SELECT a.*, d.name AS department_name
                FROM onboarding_applications a
                LEFT JOIN departments d ON a.department_id = d.id
                WHERE a.id = :id LIMIT 1";
        $row = $this->query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public function findPendingByEmail(string $email)
    {
        $stmt = $this->db->prepare("SELECT * FROM onboarding_applications WHERE email = :email AND status = 'Pending' LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function submit(array $data): int
    {
        return (int) $this->insert([
            'invite_id'          => $data['invite_id'] ?: null,
            'first_name'         => $data['first_name'],
            'last_name'          => $data['last_name'],
            'email'              => $data['email'],
            'phone'              => $data['phone'],
            'gender'             => $data['gender'] ?: null,
            'dob'                => $data['dob'] ?: null,
            'id_number'          => $data['id_number'] ?: null,
            'ethnicity'          => $data['ethnicity'] ?: null,
            'household_address'  => $data['household_address'] ?: null,
            'emergency_contact'  => $data['emergency_contact'] ?: null,
            'emergency_relation' => $data['emergency_relation'] ?: null,
            'emergency_phone'    => $data['emergency_phone'] ?: null,
            'education'          => $data['education'] ?: null,
            'address'            => $data['address'] ?: null,
            'department_id'      => $data['department_id'] ?: null,
            'designation'        => $data['designation'] ?: null,
            'expected_salary'    => (float) $data['expected_salary'],
            'notes'              => $data['notes'] ?: null,
            'status'             => 'Pending',
        ]);
    }

    public function review(int $id, string $status, ?int $reviewedBy, ?string $comment = null, ?int $hiredEmployeeId = null): bool
    {
        return (bool) $this->update($id, [
            'status'            => $status,
            'reviewed_at'       => date('Y-m-d H:i:s'),
            'reviewed_by'       => $reviewedBy,
            'review_comment'    => $comment,
            'hired_employee_id' => $hiredEmployeeId,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
}
