<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('How far are you willing to go?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('distance') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'nearby', 'label' => '📍 Nearby', 'sub' => 'Under 2 miles'],
        ['value' => 'close', 'label' => '🚗 Close', 'sub' => 'Under 5 miles'],
        ['value' => 'anywhere', 'label' => '🌍 Anywhere', 'sub' => 'Distance doesn\'t matter'],
    ] as $opt)
        <button
            wire:click="answer('distance', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
        </button>
    @endforeach
</div>
