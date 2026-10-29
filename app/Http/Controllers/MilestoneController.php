<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Models\Milestone;
use App\Models\MilestonePeriod;
use App\Services\MilestoneService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

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
            ->success(__('Milestone created.'));
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
            ->success(__('Milestone updated.'));
    }

    public function destroy(
        Request $request,
        Milestone $milestone,
    ): RedirectResponse {
        $this->ensureOwner($request, $milestone);

        $milestone->delete();

        return redirect()
            ->route('milestones.index')
            ->success(__('Milestone deleted.'));
    }

    public function history(Request $request, Milestone $milestone)
    {
        $this->ensureOwner($request, $milestone);

        $periods = $milestone->periods()
            ->orderByDesc('started_at')
            ->get();

        return view('milestones.history', compact('milestone', 'periods'));
    }

    public function start(
        Request $request,
        Milestone $milestone,
        MilestoneService $service,
    ): Response|RedirectResponse {
        if (! $request->ajax()) {
            $this->ensureOwner($request, $milestone);
            $service->start($milestone);

            return redirect()->route('milestones.index');
        }

        $error = null;
        try {
            $this->ensureOwner($request, $milestone);
            $service->start($milestone);
        } catch (HttpException $e) {
            report($e);

            return response('', $e->getStatusCode());
        } catch (QueryException $e) {
            report($e);
            $error = 'DB Error';
        } catch (Throwable $e) {
            report($e);
            $error = $e->getMessage();
        }

        return response()->view('milestones._card', [
            'milestone' => $milestone->fresh(['currentPeriod']),
            'error' => $error,
        ]);
    }

    public function stop(
        Request $request,
        Milestone $milestone,
        MilestoneService $service,
    ): Response|RedirectResponse {
        if (! $request->ajax()) {
            $this->ensureOwner($request, $milestone);
            $service->stop($milestone);

            return redirect()->route('milestones.index');
        }

        $error = null;
        try {
            $this->ensureOwner($request, $milestone);
            $service->stop($milestone);
        } catch (HttpException $e) {
            report($e);

            return response('', $e->getStatusCode());
        } catch (QueryException $e) {
            report($e);
            $error = 'DB Error';
        } catch (Throwable $e) {
            report($e);
            $error = $e->getMessage();
        }

        return response()->view('milestones._card', [
            'milestone' => $milestone->fresh(['currentPeriod']),
            'error' => $error,
        ]);
    }

    public function reset(
        Request $request,
        Milestone $milestone,
        MilestoneService $service,
    ): Response|RedirectResponse {
        if (! $request->ajax()) {
            $this->ensureOwner($request, $milestone);
            $service->reset($milestone);

            return redirect()->route('milestones.index');
        }

        $error = null;

        try {
            $this->ensureOwner($request, $milestone);
            $service->reset($milestone);
        } catch (HttpException $e) {
            report($e);

            return response('', $e->getStatusCode());
        } catch (QueryException $e) {
            report($e);
            $error = 'DB Error';
        } catch (Throwable $e) {
            report($e);
            $error = $e->getMessage();
        }

        return response()->view('milestones._card', [
            'milestone' => $milestone->fresh(['currentPeriod']),
            'error' => $error,
        ]);
    }

    public function anniversaries(Request $request): View
    {
        abort_unless($request->ajax(), 404);

        $milestones = $request->user()
            ->milestones()
            ->with('currentPeriod')
            ->get();

        return view('milestones._today-anniversaries', compact('milestones'));
    }

    private function ensureOwner(Request $request, Milestone $milestone): void
    {
        abort_unless(
            $milestone->user_id === $request->user()->id,
            403,
        );
    }
}
