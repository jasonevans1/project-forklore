<?php

use App\Enums\ModeUsed;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('History')] class extends Component {
    /** How many visits to load. */
    private const int LIMIT = 50;

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Recent visits for the authenticated user, grouped by "Month Year".
     *
     * @return array<string, list<Visit>>
     */
    #[Computed]
    public function visitsByMonth(): array
    {
        $visits = Visit::with('restaurant')
            ->where('user_id', Auth::id())
            ->orderByDesc('visited_at')
            ->limit(self::LIMIT)
            ->get();

        return $visits
            ->groupBy(fn (Visit $v) => $v->visited_at->format('F Y'))
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }
}; ?>

<div class="mx-auto max-w-lg px-4 py-8">

    <flux:heading size="xl" class="mb-6 text-2xl font-bold">
        {{ __('History') }}
    </flux:heading>

    @if (empty($this->visitsByMonth))
        <div class="flex flex-col items-center gap-3 py-16 text-center">
            <flux:heading>{{ __('No visits yet') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">
                {{ __('Your visits will appear here after you tap Going on any pick screen.') }}
            </flux:text>
        </div>
    @else
        @foreach ($this->visitsByMonth as $month => $visits)
            <section class="mb-8">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                    {{ $month }}
                </h2>

                <ul class="space-y-3">
                    @foreach ($visits as $visit)
                        <li class="flex items-start justify-between rounded-2xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold">
                                    {{ $visit->restaurant?->name ?? __('Unknown restaurant') }}
                                </span>
                                <span class="text-xs text-neutral-400">
                                    {{ $visit->visited_at->format('M j') }}
                                </span>
                            </div>
                            <flux:badge size="sm" class="shrink-0">
                                {{ match ($visit->mode_used) {
                                    ModeUsed::QuickPick  => __('Quick Pick'),
                                    ModeUsed::Quiz       => __('Quiz'),
                                    ModeUsed::Tournament => __('Tournament'),
                                    ModeUsed::Tonight    => __('Tonight'),
                                    default              => $visit->mode_used->value,
                                } }}
                            </flux:badge>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif

</div>
