@props([
    'name',
    'href' => null,
    'badgeLabel' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    {{ $attributes->class('flex items-center justify-between gap-3 bg-ticket-bg px-4 py-3 text-ticket-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-ticket-accent') }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
>
    <div class="flex flex-col gap-0.5">
        <span class="font-display">{{ $name }}</span>

        <div class="font-mono-ticket text-sm">{{ $slot }}</div>
    </div>

    @if (isset($trailing))
        <div class="shrink-0">{{ $trailing }}</div>
    @elseif ($badgeLabel)
        <flux:badge size="sm" class="shrink-0">{{ $badgeLabel }}</flux:badge>
    @endif
</{{ $tag }}>
