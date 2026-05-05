@php
    use App\Enums\IndoorVibe;
    use App\Enums\PatioQuality;
@endphp

{{-- Name --}}
<flux:input
    wire:model="name"
    :label="__('Name')"
    type="text"
    required
    autofocus
    autocomplete="off"
/>

{{-- Address --}}
<flux:input
    wire:model="address"
    :label="__('Address')"
    type="text"
    autocomplete="off"
/>

{{-- Cuisine Tags --}}
<flux:input
    wire:model="cuisine_tags"
    :label="__('Cuisine tags')"
    :description="__('Comma-separated (e.g. Italian, Pizza)')"
    type="text"
    required
/>

{{-- Vibe Tags --}}
<flux:input
    wire:model="vibe_tags"
    :label="__('Vibe tags')"
    :description="__('Comma-separated (e.g. romantic, casual)')"
    type="text"
    required
/>

{{-- Price Level --}}
<flux:select wire:model="price_level" :label="__('Price level')">
    <flux:select.option value="">{{ __('— Select —') }}</flux:select.option>
    <flux:select.option value="1">{{ __('$ – Inexpensive') }}</flux:select.option>
    <flux:select.option value="2">{{ __('$$ – Moderate') }}</flux:select.option>
    <flux:select.option value="3">{{ __('$$$ – Expensive') }}</flux:select.option>
    <flux:select.option value="4">{{ __('$$$$ – Very expensive') }}</flux:select.option>
</flux:select>

{{-- Patio Quality --}}
<flux:select wire:model="patio_quality" :label="__('Patio quality')" required>
    @foreach (PatioQuality::cases() as $case)
        <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
    @endforeach
</flux:select>

{{-- Indoor Vibe When Cold --}}
<flux:select wire:model="indoor_vibe_when_cold" :label="__('Indoor vibe when cold')" required>
    @foreach (IndoorVibe::cases() as $case)
        <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
    @endforeach
</flux:select>

{{-- Avg Duration Minutes --}}
<flux:input
    wire:model="avg_duration_minutes"
    :label="__('Average visit duration (minutes)')"
    type="number"
    min="1"
    max="600"
/>
