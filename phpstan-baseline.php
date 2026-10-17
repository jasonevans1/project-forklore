<?php declare(strict_types = 1);

$ignoreErrors = [];
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
	'message' => '#^Call to an undefined method Illuminate\\\\Database\\\\Schema\\\\ForeignKeyDefinition\\:\\:after\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/database/migrations/2026_04_30_030807_add_partner_id_to_users_table.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
