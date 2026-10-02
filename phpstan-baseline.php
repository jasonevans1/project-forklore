<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Actions\\\\Fortify\\\\CreateNewUser\\:\\:passwordRules\\(\\) should return array\\<int, array\\<mixed\\>\\|Illuminate\\\\Contracts\\\\Validation\\\\ValidationRule\\|string\\> but returns array\\<int, Illuminate\\\\Validation\\\\Rules\\\\Password\\|string\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Actions/Fortify/CreateNewUser.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Actions\\\\Fortify\\\\ResetUserPassword\\:\\:passwordRules\\(\\) should return array\\<int, array\\<mixed\\>\\|Illuminate\\\\Contracts\\\\Validation\\\\ValidationRule\\|string\\> but returns array\\<int, Illuminate\\\\Validation\\\\Rules\\\\Password\\|string\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Actions/Fortify/ResetUserPassword.php',
];
$ignoreErrors[] = [
	'message' => '#^Trait App\\\\Concerns\\\\ComputesRestaurantPresentation is used zero times and is not analysed\\.$#',
	'identifier' => 'trait.unused',
	'count' => 1,
	'path' => __DIR__ . '/app/Concerns/ComputesRestaurantPresentation.php',
];
$ignoreErrors[] = [
	'message' => '#^Trait App\\\\Concerns\\\\ValidatesEventFields is used zero times and is not analysed\\.$#',
	'identifier' => 'trait.unused',
	'count' => 1,
	'path' => __DIR__ . '/app/Concerns/ValidatesEventFields.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Livewire\\\\Actions\\\\Logout\\:\\:__invoke\\(\\) has no return type specified\\.$#',
	'identifier' => 'missingType.return',
	'count' => 1,
	'path' => __DIR__ . '/app/Livewire/Actions/Logout.php',
];
$ignoreErrors[] = [
	'message' => '#^Property App\\\\Livewire\\\\VibePicker\\:\\:\\$selected type has no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/app/Livewire/VibePicker.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Services\\\\QuickPickService\\:\\:pickFromTop\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Using nullsafe property access "\\?\\-\\>preferred_vibe_tags" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
	'identifier' => 'nullsafe.neverNull',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Using nullsafe property access "\\?\\-\\>preferred_vibe_tags" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
	'identifier' => 'nullsafe.neverNull',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Services\\\\TournamentService\\:\\:advance\\(\\) should return list\\<App\\\\Models\\\\Restaurant\\> but returns array\\<int, App\\\\Models\\\\Restaurant\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TournamentService.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Services\\\\TournamentService\\:\\:seed\\(\\) should return list\\<App\\\\Models\\\\Restaurant\\> but returns array\\<int, App\\\\Models\\\\Restaurant\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TournamentService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function rtrim expects string, bool\\|string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/config/filesystems.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method Illuminate\\\\Database\\\\Schema\\\\ForeignKeyDefinition\\:\\:index\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/database/migrations/2026_04_29_022521_create_restaurants_table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method Illuminate\\\\Database\\\\Schema\\\\ForeignKeyDefinition\\:\\:after\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/database/migrations/2026_04_30_030807_add_partner_id_to_users_table.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
