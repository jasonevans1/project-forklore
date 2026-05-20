<?php

use App\Concerns\ValidatesEventFields;
use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add event')] class extends Component {
    use ValidatesEventFields;

    public Restaurant $restaurant;

    public string $title = '';

    public string $type = '';

    public string $recurrence = EventRecurrence::Weekly->value;

    public ?int $day_of_week = null;

    public string $specific_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $description = '';

    public bool $active = true;

    public bool $shared = false;

    public function mount(Restaurant $restaurant): void
    {
        $this->authorize('update', $restaurant);
        $this->restaurant = $restaurant;
    }

    /**
     * Save the new event.
     */
    public function save(): void
    {
        $this->validate($this->eventRules());

        Event::create([
            'restaurant_id' => $this->restaurant->id,
            'owner_user_id' => Auth::id(),
            'title' => $this->title,
            'type' => $this->type,
            'recurrence' => $this->recurrence,
            'day_of_week' => $this->needsDayOfWeek() ? $this->day_of_week : null,
            'specific_date' => $this->needsSpecificDate() ? $this->specific_date : null,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'description' => $this->description ?: null,
            'active' => $this->active,
            'shared' => $this->shared,
        ]);

        Flux::toast(variant: 'success', text: __('Event added.'));

        $this->redirect(route('restaurants.events.index', $this->restaurant), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-lg px-4 py-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('Add event') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">{{ $this->restaurant->name }}</flux:text>
        </div>

        <form wire:submit="save" class="space-y-6" novalidate>

            {{-- Title --}}
            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="title" type="text" placeholder="{{ __('Wednesday Trivia') }}" />
                <flux:error name="title" />
            </flux:field>

            {{-- Type --}}
            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model="type">
                    <flux:select.option value="">{{ __('Select a type…') }}</flux:select.option>
                    @foreach (\App\Enums\EventType::cases() as $eventType)
                        <flux:select.option value="{{ $eventType->value }}">
                            {{ ucwords(str_replace('_', ' ', $eventType->value)) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            {{-- Recurrence picker --}}
            <flux:field>
                <flux:label>{{ __('Recurrence') }}</flux:label>
                <flux:select wire:model.live="recurrence">
                    @foreach (\App\Enums\EventRecurrence::cases() as $rec)
                        <flux:select.option value="{{ $rec->value }}">
                            {{ ucwords(str_replace('_', ' ', $rec->value)) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="recurrence" />
            </flux:field>

            {{-- Day of week (weekly / monthly) --}}
            @if ($this->needsDayOfWeek())
                <flux:field>
                    @if ($this->recurrence === \App\Enums\EventRecurrence::Weekly->value)
                        <flux:label>{{ __('Day of week') }}</flux:label>
                        <flux:select wire:model="day_of_week">
                            <flux:select.option value="">{{ __('Select a day…') }}</flux:select.option>
                            @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $index => $day)
                                <flux:select.option value="{{ $index }}">{{ $day }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:label>{{ __('Day of month') }}</flux:label>
                        <flux:input wire:model="day_of_week" type="number" min="1" max="31" placeholder="15" />
                    @endif
                    <flux:error name="day_of_week" />
                </flux:field>
            @endif

            {{-- Specific date (one-off) --}}
            @if ($this->needsSpecificDate())
                <flux:field>
                    <flux:label>{{ __('Date') }}</flux:label>
                    <flux:input wire:model="specific_date" type="date" />
                    <flux:error name="specific_date" />
                </flux:field>
            @endif

            {{-- Times --}}
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Start time') }}</flux:label>
                    <flux:input wire:model="start_time" type="time" />
                    <flux:error name="start_time" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('End time') }}</flux:label>
                    <flux:input wire:model="end_time" type="time" />
                    <flux:error name="end_time" />
                </flux:field>
            </div>

            {{-- Description --}}
            <flux:field>
                <flux:label>{{ __('Description') }} <span class="text-xs text-neutral-400">({{ __('optional') }})</span></flux:label>
                <flux:textarea wire:model="description" rows="3" />
                <flux:error name="description" />
            </flux:field>

            {{-- Toggles --}}
            <div class="space-y-3">
                <flux:field variant="inline">
                    <flux:label>{{ __('Active') }}</flux:label>
                    <flux:switch wire:model="active" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>{{ __('Share with other users') }}</flux:label>
                    <flux:switch wire:model="shared" />
                </flux:field>
            </div>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Add event') }}
                </flux:button>

                <flux:button :href="route('restaurants.events.index', $this->restaurant)" wire:navigate variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
