<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class ResetUserPassword
{
    public function handle(array $data): string
    {
        return Password::reset(
            $data,
            function (User $user, string $password) {
                DB::transaction(function () use ($user, $password) {
                    $user->password = $password;
                    $user->setRememberToken(Str::random(60));
                    $user->save();

                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))
                            ->table(config('session.table'))
                            ->where('user_id', $user->id)
                            ->delete();
                    }
                });

                event(new PasswordReset($user));
            }
        );
    }
}
