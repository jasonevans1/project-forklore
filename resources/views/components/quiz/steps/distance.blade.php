<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('How far are you willing to go?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('distance') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'under_2_miles', 'label' => '📍 Under 2 mi'],
        ['value' => '2_to_5_miles', 'label' => '🚗 2–5 mi'],
        ['value' => '5_to_15_miles', 'label' => '🛣️ 5–15 mi'],
        ['value' => 'anywhere', 'label' => '🌍 Anywhere'],
    ] as $opt)
        <button
            wire:click="answer('distance', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
        </button>
    @endforeach
</div>
