<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Duration extends Component
{
    public function __construct(
        public int|float $seconds,
    ) {}

    public function render(): View
    {
        return view('components.duration');
    }
}
