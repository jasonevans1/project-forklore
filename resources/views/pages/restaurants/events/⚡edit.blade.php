<?php

use App\Concerns\ValidatesEventFields;
use App\Enums\EventRecurrence;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Restaurant;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit event')] class extends Component {
    use ValidatesEventFields;
    public Restaurant $restaurant;

    public int $eventId;

    public string $title = '';

    public string $type = '';

    public string $recurrence = '';

    public ?int $day_of_week = null;

    public string $specific_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $description = '';

    public bool $active = true;

    public bool $shared = false;

    public function mount(Restaurant $restaurant, Event $event): void
    {
        $this->authorize('update', $restaurant);
        $this->authorize('update', $event);

        $this->restaurant = $restaurant;
        $this->eventId = $event->id;
        $this->title = $event->title;
        $this->type = $event->type->value;
        $this->recurrence = $event->recurrence->value;
        $this->day_of_week = $event->day_of_week;
        $this->specific_date = $event->specific_date?->format('Y-m-d') ?? '';
        $this->start_time = substr($event->start_time, 0, 5);
        $this->end_time = substr($event->end_time, 0, 5);
        $this->description = $event->description ?? '';
        $this->active = $event->active;
        $this->shared = $event->shared;
    }

    /**
     * Save the updated event.
     */
    public function save(): void
    {
        $event = Event::findOrFail($this->eventId);

        $this->authorize('update', $this->restaurant);
        $this->authorize('update', $event);

        $this->validate($this->eventRules());

        $event->update([
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

        Flux::toast(variant: 'success', text: __('Event updated.'));

        $this->redirect(route('restaurants.events.index', $this->restaurant), navigate: true);
    }

    /**
     * Delete this event.
     */
    public function delete(): void
    {
        $event = Event::findOrFail($this->eventId);

        $this->authorize('update', $this->restaurant);
        $this->authorize('delete', $event);

        $event->delete();

        Flux::toast(variant: 'success', text: __('Event deleted.'));

        $this->redirect(route('restaurants.events.index', $this->restaurant), navigate: true);
    }

}; ?>

<section class="w-full">
    <div class="mx-auto max-w-lg px-4 py-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('Edit event') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">{{ $this->restaurant->name }}</flux:text>
        </div>

        <form wire:submit="save" class="space-y-6" novalidate>

            {{-- Title --}}
            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="title" type="text" />
                <flux:error name="title" />
            </flux:field>

            {{-- Type --}}
            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model="type">
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
                        <flux:input wire:model="day_of_week" type="number" min="1" max="31" />
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
                    {{ __('Save changes') }}
                </flux:button>

                <flux:button :href="route('restaurants.events.index', $this->restaurant)" wire:navigate variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>

        {{-- Delete --}}
        <div class="mt-10 border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <flux:heading size="lg" class="mb-2 text-red-600 dark:text-red-400">{{ __('Danger zone') }}</flux:heading>
            <flux:text class="mb-4 text-neutral-500 dark:text-neutral-400">
                {{ __('Deleting this event cannot be undone.') }}
            </flux:text>

            <flux:modal.trigger name="delete-event">
                <flux:button variant="danger">{{ __('Delete event') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:modal name="delete-event" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete event?') }}</flux:heading>
            <flux:text>{{ __('This action cannot be undone.') }}</flux:text>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button>{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
