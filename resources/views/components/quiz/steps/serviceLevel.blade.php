<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('What kind of night is it?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('serviceLevel') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'quick_easy', 'label' => '⚡ Quick and easy'],
        ['value' => 'casual_sit_down', 'label' => '🍴 Casual sit-down'],
        ['value' => 'nicer_night_out', 'label' => '🥂 Nicer night out'],
        ['value' => 'special_occasion', 'label' => '🎉 Special occasion'],
    ] as $opt)
        <button
            wire:click="answer('serviceLevel', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
        </button>
    @endforeach
</div>
