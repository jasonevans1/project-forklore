@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Forklore" {{ $attributes }} />
@else
    <flux:brand name="Forklore" {{ $attributes }} />
@endif
