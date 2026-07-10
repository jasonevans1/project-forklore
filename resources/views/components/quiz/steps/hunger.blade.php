<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('How hungry are you?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('hunger') }}</p>
<div class="flex flex-col gap-3">
    @foreach ([
        ['value' => 'light', 'label' => '🥗 Light bite', 'sub' => 'Snack or small plates'],
        ['value' => 'moderate', 'label' => '🍝 Moderate', 'sub' => 'A solid meal'],
        ['value' => 'hungry', 'label' => '🥩 Very hungry', 'sub' => 'Big portions, hearty food'],
    ] as $opt)
        <button
            wire:click="answer('hunger', '{{ $opt['value'] }}')"
            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
        >
            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
        </button>
    @endforeach
</div>
