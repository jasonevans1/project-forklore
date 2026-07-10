<flux:heading size="xl" class="text-2xl font-bold">
    {{ __("What's your energy tonight?") }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('energy') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'lively', 'label' => '🎉 Lively', 'sub' => 'Busy, loud, fun'],
        ['value' => 'moderate', 'label' => '😊 Moderate', 'sub' => 'Relaxed but social'],
        ['value' => 'quiet', 'label' => '🤫 Quiet', 'sub' => 'Low-key, easy conversation'],
    ] as $opt)
        <button
            wire:click="answer('energy', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
        </button>
    @endforeach
</div>
