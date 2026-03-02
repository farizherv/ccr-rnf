<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CcrReport;
use App\Models\User;

class CcrReportPolicy
{
    /**
     * Boleh edit report jika:
     * - Director (bisa edit semua)
     * - Admin (bisa edit semua)
     * - Owner (creator report)
     * - Report lama tanpa created_by (siapapun boleh)
     */
    public function update(User $user, CcrReport $report): bool
    {
        // Director & Admin bisa edit semua
        $role = $user->role instanceof UserRole ? $user->role->value : strtolower(trim((string) $user->role));
        if (in_array($role, ['director', 'admin'], true)) {
            return true;
        }

        // Report lama tanpa created_by → allow (backward compat)
        if ($report->created_by === null) {
            return true;
        }

        // Owner check
        return (int) $user->id === (int) $report->created_by;
    }

    /**
     * Boleh hapus report jika:
     * - Admin / Director
     * - Owner (creator report)
     * - Report lama tanpa created_by
     */
    public function delete(User $user, CcrReport $report): bool
    {
        return $this->update($user, $report);
    }

    /**
     * Boleh submit ke direktur jika:
     * - Owner (creator report)
     * - Admin (bisa submit milik siapapun)
     * - Report lama tanpa created_by
     */
    public function submit(User $user, CcrReport $report): bool
    {
        $role = $user->role instanceof UserRole ? $user->role->value : strtolower(trim((string) $user->role));
        if ($role === 'admin') {
            return true;
        }

        if ($report->created_by === null) {
            return true;
        }

        return (int) $user->id === (int) $report->created_by;
    }
}
