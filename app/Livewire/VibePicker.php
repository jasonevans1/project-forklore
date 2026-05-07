<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class VibePicker extends Component
{
    #[Modelable]
    public array $selected = [];

    public function toggle(string $tag): void
    {
        if (in_array($tag, $this->selected)) {
            $this->selected = array_values(array_filter($this->selected, fn (string $t): bool => $t !== $tag));
        } else {
            $this->selected[] = $tag;
        }
    }

    public function render(): View
    {
        return view('livewire.vibe-picker', ['dimensions' => config('vibes')]);
    }
}
