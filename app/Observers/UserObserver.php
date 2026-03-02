<?php

namespace App\Observers;

use App\Models\NotificationRecipient;
use App\Models\User;
use App\Support\Notifications\NotificationRecipientDefaults;

class UserObserver
{
    public function created(User $user): void
    {
        $email = $this->normalizeEmail($user->email);
        if ($email === '') {
            return;
        }

        $defaults = NotificationRecipientDefaults::flagsForRole($user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role);
        $actorId = $this->actorId();

        NotificationRecipient::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $this->cleanName((string) $user->name),
                'is_active' => true,
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
        if (!$user->wasChanged(['email', 'name', 'role'])) {
            return;
        }

        $newEmail = $this->normalizeEmail($user->email);
        if ($newEmail === '') {
            return;
        }

        $oldEmail = $this->normalizeEmail((string) $user->getOriginal('email'));

        $recipient = null;
        if ($oldEmail !== '' && $oldEmail !== $newEmail) {
            $recipient = NotificationRecipient::query()
                ->whereRaw('LOWER(email) = ?', [$oldEmail])
                ->first();
        }

        if (!$recipient) {
            $recipient = NotificationRecipient::query()
                ->whereRaw('LOWER(email) = ?', [$newEmail])
                ->first();
        }

        if (!$recipient) {
            $this->created($user);
            return;
        }

        $updates = [
            'email' => $newEmail,
            'name' => $this->cleanName((string) $user->name),
            'updated_by' => $this->actorId(),
        ];

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

    private function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
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

