<?php

use App\Livewire\VibePicker;
use Livewire\Component;
use Livewire\Livewire;

it('renders a chip for every tag defined in the vibes config', function () {
    $allTags = collect(config('vibes'))->flatten()->all();

    $component = Livewire::test(VibePicker::class);

    foreach ($allTags as $tag) {
        $component->assertSee($tag);
    }
});

it('marks a chip as selected when its tag is in the selected array', function () {
    Livewire::test(VibePicker::class, ['selected' => ['casual']])
        ->assertSeeHtml('bg-[var(--color-accent)]');
});

it('adds a tag to selected when toggle is called and the tag is not yet selected', function () {
    Livewire::test(VibePicker::class)
        ->call('toggle', 'casual')
        ->assertSet('selected', ['casual']);
});

it('removes a tag from selected when toggle is called and the tag is already selected', function () {
    Livewire::test(VibePicker::class, ['selected' => ['casual', 'lively']])
        ->call('toggle', 'casual')
        ->assertSet('selected', ['lively']);
});

it('accepts an initial selected array via wire:model binding from a parent component', function () {
    $parentComponent = new class extends Component
    {
        public array $vibe_tags = ['lively', 'casual'];

        public function render(): string
        {
            return '<div><livewire:vibe-picker wire:model="vibe_tags" /></div>';
        }
    };

    Livewire::test($parentComponent)
        ->assertSeeHtml('bg-[var(--color-accent)]');
});

it('renders tags grouped under their dimension label (energy, occasion, experience)', function () {
    Livewire::test(VibePicker::class)
        ->assertSee('energy')
        ->assertSee('occasion')
        ->assertSee('experience');
});

it('humanizes underscored tag values for display (date_night renders as "date night")', function () {
    Livewire::test(VibePicker::class)
        ->assertSee('date night')
        ->assertDontSeeHtml('<span>date_night</span>');
});
