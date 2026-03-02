<?php

namespace App\Observers;

use App\Models\NotificationRecipient;
use App\Models\User;
use App\Support\Notifications\NotificationRecipientDefaults;

class UserObserver
{
    public function created(User $user): void
    {
        $defaults = NotificationRecipientDefaults::flagsForRole($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role);
        $actorId = $this->actorId();

        // Create recipient linked by user_id with null email.
        // Admin must fill in the real email on the website.
        NotificationRecipient::query()->firstOrCreate(
            ['user_id' => (int) $user->id],
            [
                'email' => null,
                'name' => $this->cleanName((string) $user->name),
                'is_active' => false,
                'notify_waiting' => $defaults['notify_waiting'],
                'notify_approved' => $defaults['notify_approved'],
                'notify_rejected' => $defaults['notify_rejected'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );
    }

    public function updated(User $user): void
    {
        if (!$user->wasChanged(['name', 'role'])) {
            return;
        }

        // Find by user_id (proper link)
        $recipient = NotificationRecipient::query()
            ->where('user_id', (int) $user->id)
            ->first();

        if (!$recipient) {
            // If no recipient linked yet, create one (inactive, no email)
            $this->created($user);
            return;
        }

        $updates = [
            'name' => $this->cleanName((string) $user->name),
            'updated_by' => $this->actorId(),
        ];

        // Do NOT overwrite the manually-set email

        if ($user->wasChanged('role')) {
            $origRole = $user->getOriginal('role');
            $oldDefaults = NotificationRecipientDefaults::flagsForRole($origRole instanceof \App\Enums\UserRole ? $origRole->value : (string) $origRole);

            $stillOnOldDefaults =
                ((bool) $recipient->notify_waiting === (bool) $oldDefaults['notify_waiting']) &&
                ((bool) $recipient->notify_approved === (bool) $oldDefaults['notify_approved']) &&
                ((bool) $recipient->notify_rejected === (bool) $oldDefaults['notify_rejected']);

            if ($stillOnOldDefaults) {
                $newDefaults = NotificationRecipientDefaults::flagsForRole($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role);
                $updates = array_merge($updates, $newDefaults);
            }
        }

        $recipient->fill($updates);
        $recipient->save();
    }

    private function cleanName(string $name): string
    {
        $clean = trim($name);
        if ($clean === '') {
            return '';
        }

        if (mb_strlen($clean) <= 120) {
            return $clean;
        }

        return rtrim(mb_substr($clean, 0, 120));
    }

    private function actorId(): ?int
    {
        $id = auth()->id();
        return is_numeric($id) ? (int) $id : null;
    }
}
