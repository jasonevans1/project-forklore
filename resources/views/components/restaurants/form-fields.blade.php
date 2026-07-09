@php
    use App\Enums\IndoorVibe;
    use App\Enums\PatioQuality;
    use App\Enums\PrimaryCuisine;
    use App\Enums\ServiceLevel;
    use App\Enums\ServiceOption;
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
<div>
    <flux:label class="mb-2">{{ __('Vibe tags') }}</flux:label>
    <livewire:vibe-picker wire:model="vibe_tags" />
    @error('vibe_tags') <flux:error :message="$message" /> @enderror
    @error('vibe_tags.*') <flux:error :message="$message" /> @enderror
</div>

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

{{-- Service Level --}}
<flux:select wire:model="service_level" :label="__('Service level')">
    <flux:select.option value="">{{ __('— Select —') }}</flux:select.option>
    @foreach (ServiceLevel::cases() as $case)
        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
    @endforeach
</flux:select>

{{-- Primary Cuisine --}}
<flux:select wire:model="primary_cuisine" :label="__('Primary cuisine')">
    <flux:select.option value="">{{ __('— Select —') }}</flux:select.option>
    @foreach (PrimaryCuisine::cases() as $case)
        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
    @endforeach
</flux:select>

{{-- Service Options --}}
<flux:checkbox.group wire:model="service_options" :label="__('Service options')">
    @foreach (ServiceOption::cases() as $case)
        <flux:checkbox value="{{ $case->value }}" label="{{ $case->label() }}" />
    @endforeach
</flux:checkbox.group>
