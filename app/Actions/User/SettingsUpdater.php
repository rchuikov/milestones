<?php

namespace App\Actions\User;

use App\Models\User;

final class SettingsUpdater
{
    public function update(User $user, array $data): void
    {
        $user->name = $data['name'];
        $user->save();
    }
}
