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
        $userId = (int) $user->id;
        $senderId = (int) $correspondence->sender_id;

        return $senderId === $userId;
    }

    public function send(User $user, Correspondence $correspondence): bool
    {
        $userId = (int) $user->id;
        $senderId = (int) $correspondence->sender_id;

        return $senderId === $userId;
    }

    public function attach(User $user, Correspondence $correspondence): bool
    {
        $userId = (int) $user->id;
        $senderId = (int) $correspondence->sender_id;

        return $senderId === $userId;
    }

    public function addRecipient(User $user, Correspondence $correspondence): bool
    {
        $userId = (int) $user->id;
        $senderId = (int) $correspondence->sender_id;

        return $senderId === $userId;
    }

    public function delete(User $user, Correspondence $correspondence): bool
    {
        $userId = (int) $user->id;
        $senderId = (int) $correspondence->sender_id;

        if ($senderId !== $userId && !$user->can('manage-correspondences')) {
            return false;
        }

        return !$correspondence->has_been_seen;
    }

    public function createChildAssignment(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->assignments()->where('assigned_to_id', $user->id)->exists();
    }

    private function isParticipant(User $user, Correspondence $correspondence): bool
    {
        $userId = (int) $user->id;
        $senderId = (int) $correspondence->sender_id;

        $isParticipant = $senderId === $userId
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
