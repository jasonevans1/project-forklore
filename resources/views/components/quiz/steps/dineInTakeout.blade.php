<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('Dine in or takeout?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('dineInTakeout') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'dine_in', 'label' => '🍽️ Dine in'],
        ['value' => 'takeout', 'label' => '🥡 Takeout'],
        ['value' => 'either', 'label' => '🤷 Either is fine'],
    ] as $opt)
        <button
            wire:click="answer('dineInTakeout', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
        </button>
    @endforeach
</div>
