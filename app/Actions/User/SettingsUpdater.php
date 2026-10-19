<?php

namespace App\Actions\User;

use App\Enums\Locale;
use App\Models\User;

final class SettingsUpdater
{
    public function update(User $user, array $data): void
    {
        $user->name = $data['name'];
        $user->locale = Locale::from($data['locale']);
        $user->save();
    }
}
