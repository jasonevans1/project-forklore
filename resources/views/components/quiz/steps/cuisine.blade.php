<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('Any cuisine in mind?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('cuisine') }}</p>
<div class="flex flex-col gap-3">
    <button
        wire:click="answer('cuisine', null)"
        class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
    >
        <span class="text-lg font-semibold">🎲 {{ __('Surprise me') }}</span>
        <span class="text-sm text-neutral-500">{{ __('Pick the best match regardless of cuisine') }}</span>
    </button>
    @foreach (['Italian', 'Mexican', 'American', 'Thai', 'Japanese', 'Indian', 'Chinese', 'Mediterranean'] as $cuisine)
        <button
            wire:click="answer('cuisine', '{{ $cuisine }}')"
            class="rounded-2xl border border-zinc-200 p-5 text-left text-lg font-semibold dark:border-zinc-700"
        >
            {{ $cuisine }}
        </button>
    @endforeach
</div>
