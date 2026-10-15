<?php

namespace App\Actions\User;

use App\Services\VerificationEmailSender;
use Illuminate\Auth\Events\Registered;

final class SendVerificationEmail
{
    public function __construct(private VerificationEmailSender $sender) {}

    public function handle(Registered $event): void
    {
        $this->sender->send($event->user);
    }
}
