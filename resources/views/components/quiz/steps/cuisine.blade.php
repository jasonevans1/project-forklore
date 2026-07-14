<flux:heading size="xl" class="text-2xl font-bold">
    {{ __('Any cuisine in mind?') }}
</flux:heading>
<p class="text-sm text-neutral-500">{{ __('cuisine') }}</p>
<button
    wire:click="answer('cuisine', null)"
    class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
>
    <span class="text-lg font-semibold">🎲 {{ __('Surprise me') }}</span>
    <span class="text-sm text-neutral-500">{{ __('Pick the best match regardless of cuisine') }}</span>
</button>
<div class="grid grid-cols-4 gap-3">
    @foreach ([
        \App\Enums\PrimaryCuisine::American,
        \App\Enums\PrimaryCuisine::Italian,
        \App\Enums\PrimaryCuisine::Mexican,
        \App\Enums\PrimaryCuisine::Chinese,
        \App\Enums\PrimaryCuisine::Japanese,
        \App\Enums\PrimaryCuisine::Thai,
        \App\Enums\PrimaryCuisine::Vietnamese,
        \App\Enums\PrimaryCuisine::Korean,
        \App\Enums\PrimaryCuisine::Indian,
        \App\Enums\PrimaryCuisine::Mediterranean,
        \App\Enums\PrimaryCuisine::Bbq,
        \App\Enums\PrimaryCuisine::Seafood,
        \App\Enums\PrimaryCuisine::Pizza,
        \App\Enums\PrimaryCuisine::Breakfast,
        \App\Enums\PrimaryCuisine::Cafe,
        \App\Enums\PrimaryCuisine::BarFood,
    ] as $cuisine)
        <button
            wire:click="answer('cuisine', '{{ $cuisine->value }}')"
            class="rounded-2xl border border-zinc-200 p-3 text-center text-sm font-semibold dark:border-zinc-700"
        >
            {{ $cuisine->label() }}
        </button>
    @endforeach
</div>
