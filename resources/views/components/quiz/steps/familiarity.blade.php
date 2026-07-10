<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('Something new or a familiar spot?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('familiar') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'new', 'label' => '🗺️ Something new', 'sub' => 'A place you haven\'t tried lately'],
        ['value' => 'familiar', 'label' => '🏠 A favorite', 'sub' => 'Somewhere you love and trust'],
        ['value' => 'either', 'label' => '🤷 Either', 'sub' => 'Surprise me'],
    ] as $opt)
        <button
            wire:click="answer('familiarity', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
        </button>
    @endforeach
</div>
