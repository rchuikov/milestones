<?php

namespace App\Http\Controllers;

use App\Actions\User\SettingsUpdater;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit');
    }

    public function update(UpdateSettingsRequest $request, SettingsUpdater $settingsUpdater): RedirectResponse
    {
        $settingsUpdater->update($request->user(), $request->validated());

        return back()->success('Settings updated.');
    }
}
