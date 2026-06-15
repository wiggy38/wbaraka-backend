<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\JournalAdmin;

class JournalService
{
    public function log(Admin $admin, string $action, string $cibleType, ?string $cibleId = null, ?array $details = null): void
    {
        JournalAdmin::create([
            'id_admin'   => $admin->id,
            'action'     => $action,
            'cible_type' => $cibleType,
            'cible_id'   => $cibleId,
            'details'    => $details,
            'created_at' => now(),
        ]);
    }
}
