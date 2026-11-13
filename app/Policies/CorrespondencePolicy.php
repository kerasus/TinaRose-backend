<?php

namespace App\Policies;

use App\Enums\Correspondence\CorrespondenceConfidentiality;
use App\Models\Correspondence;
use App\Models\User;

class CorrespondencePolicy
{
    public function view(User $user, Correspondence $correspondence): bool
    {
        return $this->isParticipant($user, $correspondence);
    }

    public function update(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->sender_id === $user->id;
    }

    public function send(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->sender_id === $user->id;
    }

    public function attach(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->sender_id === $user->id;
    }

    public function addRecipient(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->sender_id === $user->id;
    }

    public function createChildAssignment(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->assignments()->where('assigned_to_id', $user->id)->exists();
    }

    private function isParticipant(User $user, Correspondence $correspondence): bool
    {
        $isParticipant = $correspondence->sender_id === $user->id
            || $correspondence->recipients()->where('user_id', $user->id)->exists()
            || $correspondence->assignments()->where('assigned_to_id', $user->id)->exists();

        if (!$isParticipant) {
            return false;
        }

        if ($correspondence->confidentiality === CorrespondenceConfidentiality::CONFIDENTIAL) {
            return $isParticipant;
        }

        return true;
    }

    public function manage(User $user, Correspondence $correspondence): bool
    {
        if (!$user->can('manage-correspondences')) {
            return false;
        }

        // Confidential: manager can access only if participant
        return $this->isParticipant($user, $correspondence);
    }
}
