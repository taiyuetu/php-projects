<?php
/**
 * Onboarding invite tokens.
 *
 * Each QR code points to /apply/form/{token}. Only one invite is active at a
 * time; regenerating deactivates all previous tokens.
 */
class OnboardingInvite extends Model
{
    protected string $table = 'onboarding_invites';

    public function findActive()
    {
        $stmt = $this->db->prepare("SELECT * FROM onboarding_invites WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch();
    }

    public function findActiveByToken(string $token)
    {
        $stmt = $this->db->prepare("SELECT * FROM onboarding_invites WHERE token = :token AND is_active = 1 LIMIT 1");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Deactivate every existing invite and issue a fresh token.
     */
    public function issue(?int $createdBy = null): array
    {
        $this->db->exec("UPDATE onboarding_invites SET is_active = 0");
        $token = bin2hex(random_bytes(16));
        $this->insert([
            'token'      => $token,
            'is_active'  => 1,
            'created_by' => $createdBy,
        ]);
        return $this->findActive();
    }

    /**
     * Return the currently active invite, creating one if none exists.
     */
    public function ensureActive(?int $createdBy = null): array
    {
        $invite = $this->findActive();
        if (!$invite) {
            $invite = $this->issue($createdBy);
        }
        return $invite;
    }
}
