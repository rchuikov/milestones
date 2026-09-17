<?php

namespace App\Http\Controllers;

use App\Actions\User\ProfileUpdater;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(
        UpdateProfileRequest $request,
        ProfileUpdater $profileUpdater,
    ): RedirectResponse {
        $emailChanged = $profileUpdater->update(
            $request->user(),
            $request->validated(),
        );

        return $emailChanged
            ? redirect()->route('verification.notice')
            : back()->with('status', 'Profile updated.');
    }
}
