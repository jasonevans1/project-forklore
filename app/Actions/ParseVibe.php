<?php

namespace App\Actions;

use App\Enums\PrimaryCuisine;
use App\Services\JevService;

class ParseVibe
{
    private const NO_CUISINE = 'none';

    public function __construct(public JevService $jev) {}

    /**
     * Ask Jev to turn a free-text vibe into quiz answers. A key is present only
     * when Jev answered that field confidently with an allowed value; a
     * confident "no cuisine craving" is a present null. Null means Jev is unavailable.
     *
     * @return ?array{energy?: string, hunger?: string, familiarity?: string, cuisine?: ?string, serviceLevel?: string, dineInTakeout?: string}
     */
    public function execute(string $vibe): ?array
    {
        $questions = $this->questions();
        $answers = $this->jev->ask(['vibe' => $vibe], $questions);

        if ($answers === null) {
            return null;
        }

        $parsed = [];

        foreach ($questions as $field => $question) {
            $value = $answers[$field]['value'] ?? null;

            if (! is_string($value) || ! array_key_exists($value, $question['criteria'])) {
                continue;
            }

            $parsed[$field] = $value === self::NO_CUISINE && $field === 'cuisine' ? null : $value;
        }

        return $parsed;
    }

    /**
     * @return array<string, array{type: string, instructions: string, criteria: array<string, string>}>
     */
    private function questions(): array
    {
        $cuisines = [];
        foreach (PrimaryCuisine::cases() as $cuisine) {
            if ($cuisine !== PrimaryCuisine::Other) {
                $cuisines[$cuisine->value] = $cuisine->label();
            }
        }
        $cuisines[self::NO_CUISINE] = 'No particular cuisine craving';

        return [
            'energy' => $this->choice('How much energy do they want from the place?', [
                'lively' => 'Lively and buzzing',
                'moderate' => 'Somewhere in between',
                'quiet' => 'Quiet and relaxed',
            ]),
            'hunger' => $this->choice('How hungry are they?', [
                'quick_bite' => 'Just a quick bite',
                'full_meal' => 'A full meal',
                'feast' => 'Starving, a feast',
            ]),
            'familiarity' => $this->choice('Do they want somewhere new or somewhere familiar?', [
                'new' => 'Somewhere new',
                'familiar' => 'A familiar favorite',
                'either' => 'No preference',
            ]),
            'cuisine' => $this->choice('Which cuisine are they craving, if any?', $cuisines),
            'serviceLevel' => $this->choice('What kind of occasion is it? A date night suggests a nicer night out.', [
                'quick_easy' => 'Quick and easy',
                'casual_sit_down' => 'Casual sit-down',
                'nicer_night_out' => 'Nicer night out',
                'special_occasion' => 'Special occasion',
            ]),
            'dineInTakeout' => $this->choice('Do they want to eat in or take it out?', [
                'dine_in' => 'Dine in',
                'takeout' => 'Takeout',
                'either' => 'Either',
            ]),
        ];
    }

    /**
     * @param  array<string, string>  $criteria
     * @return array{type: string, instructions: string, criteria: array<string, string>}
     */
    private function choice(string $instructions, array $criteria): array
    {
        return ['type' => 'choice', 'instructions' => $instructions, 'criteria' => $criteria];
    }
}
