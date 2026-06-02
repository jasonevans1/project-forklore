<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Preferences')] class extends Component {
    /** @var list<string> */
    public array $preferredVibeTags = [];

    public function mount(): void
    {
        $this->preferredVibeTags = Auth::user()->preferred_vibe_tags ?? [];
    }

    public function save(): void
    {
        $allTags = collect(config('vibes'))->flatten()->all();

        $this->validate([
            'preferredVibeTags' => ['array'],
            'preferredVibeTags.*' => ['string', Rule::in($allTags)],
        ]);

        Auth::user()->update(['preferred_vibe_tags' => $this->preferredVibeTags]);

        Flux::toast(variant: 'success', text: __('Preferences saved.'));
    }
}; ?>

<x-settings.layout :heading="__('Preferences')" :subheading="__('Tags that describe your ideal night out. Used to personalise picks when it\'s your turn.')">
    <form wire:submit="save" class="space-y-6">

        @php
            $allTags = collect(config('vibes'))->map(fn ($tags, $group) => ['group' => $group, 'tags' => $tags])->values();
        @endphp

        @foreach ($allTags as $group)
            <div>
                <flux:label class="mb-2 capitalize">{{ __($group['group']) }}</flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($group['tags'] as $tag)
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm transition-colors
                            {{ in_array($tag, $preferredVibeTags) ? 'border-zinc-800 bg-zinc-800 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input
                                type="checkbox"
                                value="{{ $tag }}"
                                wire:model="preferredVibeTags"
                                class="sr-only"
                            />
                            {{ $tag }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        @error('preferredVibeTags.*')
            <flux:text class="text-red-500">{{ $message }}</flux:text>
        @enderror

        <flux:button type="submit" variant="primary">
            {{ __('Save preferences') }}
        </flux:button>

    </form>
</x-settings.layout>
