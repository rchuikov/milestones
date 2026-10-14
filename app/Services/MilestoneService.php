<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\MilestonePeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MilestoneService
{
    public function create(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $milestone = $user->milestones()->create([
                'name' => $data['name'],
                'show_round_days' => $data['show_round_days'],
                'show_half_decade_days' => $data['show_half_decade_days'],
                'show_round_weeks' => $data['show_round_weeks'],
                'show_round_months' => $data['show_round_months'],
                'show_years' => $data['show_years'],
            ]);

            $milestone->periods()->create([
                'started_at' => $data['started_at'],
            ]);
        });
    }

    public function update(Milestone $milestone, array $data): void
    {
        DB::transaction(function () use ($milestone, $data) {
            $milestone->update([
                'name' => $data['name'],
                'show_round_days' => $data['show_round_days'],
                'show_half_decade_days' => $data['show_half_decade_days'],
                'show_round_weeks' => $data['show_round_weeks'],
                'show_round_months' => $data['show_round_months'],
                'show_years' => $data['show_years'],
            ]);

            if (array_key_exists('started_at', $data)) {
                $period = $milestone->currentPeriod;

                if ($period === null || $milestone->periods()->count() !== 1) {
                    throw new RuntimeException('Milestone start date cannot be changed.');
                }

                $period->started_at = $data['started_at'];
                $period->save();
            }
        });
    }

    public function start(Milestone $milestone): void
    {
        if ($milestone->currentPeriod !== null) {
            throw new RuntimeException('Milestone already has a current period.');
        }

        $period = new MilestonePeriod;
        $period->started_at = now();
        $milestone->periods()->save($period);
    }

    public function stop(Milestone $milestone): void
    {
        if (! $period = $milestone->currentPeriod) {
            throw new RuntimeException('Milestone does not have a current period.');
        }

        $period->finished_at = now();
        $period->save();
    }

    public function reset(Milestone $milestone): void
    {
        if (! $period = $milestone->currentPeriod) {
            throw new RuntimeException('Milestone does not have a current period.');
        }

        DB::transaction(function () use ($milestone, $period) {
            $now = now();
            $period->finished_at = $now;
            $period->save();
            $newPeriod = new MilestonePeriod;
            $newPeriod->started_at = $now;
            $milestone->periods()->save($newPeriod);
        });
    }
}
