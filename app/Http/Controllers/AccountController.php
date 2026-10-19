<?php

namespace App\Http\Controllers;

use App\Actions\User\AccountUpdater;
use App\Http\Requests\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.edit');
    }

    public function update(
        UpdateAccountRequest $request,
        AccountUpdater $accountUpdater,
    ): RedirectResponse {
        $isSettingPassword = $request->user()->password === null;

        $emailChanged = $accountUpdater->update(
            $request->user(),
            $request->validated(),
        );

        return $emailChanged
            ? redirect()->route('verification.notice')
            : back()->success($isSettingPassword
                ? __('Password set. You can now edit your account and sign in with your email.')
                : __('Account updated.'));
    }
}
