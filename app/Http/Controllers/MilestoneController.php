<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Models\Milestone;
use App\Models\MilestonePeriod;
use App\Services\MilestoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MilestoneController extends Controller
{
    public function index(Request $request): View
    {
        $milestones = $request->user()
            ->milestones()
            ->with('currentPeriod')
            ->get();

        return view('milestones.index', compact('milestones'));
    }

    public function create(): View
    {
        $milestone = new Milestone;
        $period = new MilestonePeriod;
        $period->started_at = now();
        $canEditStartedAt = true;

        return view('milestones.form', compact('milestone', 'period', 'canEditStartedAt'));
    }

    public function store(MilestoneRequest $request, MilestoneService $service): RedirectResponse
    {
        $service->create($request->user(), $request->validated());

        return redirect()
            ->route('milestones.index')
            ->with('status', 'Milestone created.');
    }

    public function edit(Request $request, Milestone $milestone): View
    {
        $this->ensureOwner($request, $milestone);
        $period = $milestone->currentPeriod;

        $canEditStartedAt = $period && $milestone->periods()->count() === 1;

        return view('milestones.form', compact('milestone', 'period', 'canEditStartedAt'));
    }

    public function update(
        MilestoneRequest $request,
        Milestone $milestone,
        MilestoneService $service,
    ): RedirectResponse {
        $service->update($milestone, $request->validated());

        return redirect()
            ->route('milestones.index')
            ->with('status', 'Milestone updated.');
    }

    public function destroy(
        Request $request,
        Milestone $milestone,
    ): RedirectResponse {
        $this->ensureOwner($request, $milestone);

        $milestone->delete();

        return redirect()
            ->route('milestones.index')
            ->with('status', 'Milestone deleted.');
    }

    public function history(Request $request, Milestone $milestone)
    {
        $this->ensureOwner($request, $milestone);

        $periods = $milestone->periods()
            ->orderByDesc('started_at')
            ->get();

        return view('milestones.history', compact('milestone', 'periods'));
    }

    public function start(Request $request, Milestone $milestone, MilestoneService $service): RedirectResponse
    {
        $this->ensureOwner($request, $milestone);

        $service->start($milestone);

        return redirect()->route('milestones.index');
    }

    public function stop(Request $request, Milestone $milestone, MilestoneService $service): RedirectResponse
    {
        $this->ensureOwner($request, $milestone);

        $service->stop($milestone);

        return redirect()->route('milestones.index');
    }

    public function reset(Request $request, Milestone $milestone, MilestoneService $service): RedirectResponse
    {
        $this->ensureOwner($request, $milestone);

        $service->reset($milestone);

        return redirect()->route('milestones.index');
    }

    private function ensureOwner(Request $request, Milestone $milestone): void
    {
        abort_unless(
            $milestone->user_id === $request->user()->id,
            403,
        );
    }
}
