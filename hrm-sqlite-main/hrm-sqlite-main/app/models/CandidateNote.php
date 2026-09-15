<?php
class CandidateNote extends Model
{
    protected string $table = 'candidate_notes';

    public function forCandidate(int $candidateId): array
    {
        $sql = "SELECT cn.*, u.username as author_username, u.role as author_role
                FROM candidate_notes cn
                LEFT JOIN users u ON cn.user_id = u.id
                WHERE cn.candidate_id = :cid
                ORDER BY cn.id DESC";
        return $this->query($sql, ['cid' => $candidateId])->fetchAll();
    }

    public function addNote(int $candidateId, ?int $userId, string $stage, string $note): int
    {
        return (int) $this->insert([
            'candidate_id' => $candidateId,
            'user_id'      => $userId,
            'stage'        => $stage,
            'note'         => trim($note),
        ]);
    }
}
