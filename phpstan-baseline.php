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
	'message' => '#^Strict comparison using \\!\\=\\= between string and App\\\\Enums\\\\RestaurantSource\\:\\:Places will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/app/Actions/PromotePlacesToFavorite.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/app/Actions/PromotePlacesToFavorite.php',
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
	'message' => '#^Cannot call method toDateString\\(\\) on string\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\EventRecurrence\\:\\:Monthly is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\EventRecurrence\\:\\:OneOff is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\EventRecurrence\\:\\:Weekly is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Match expression does not handle remaining value\\: string$#',
	'identifier' => 'match.unhandled',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Event\\:\\:owner\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Event\\:\\:restaurant\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Event.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\HouseholdState\\:\\:lastPicker\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/HouseholdState.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\HouseholdState\\:\\:lastPickerFor\\(\\) should return App\\\\Models\\\\User\\|null but returns Illuminate\\\\Database\\\\Eloquent\\\\Model\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/HouseholdState.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\HouseholdState\\:\\:user\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/HouseholdState.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:events\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\HasMany does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:scopeFavorites\\(\\) has parameter \\$query with generic class Illuminate\\\\Database\\\\Eloquent\\\\Builder but does not specify its types\\: TModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:scopeFavorites\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Builder does not specify its types\\: TModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:scopeOwnedBy\\(\\) has parameter \\$query with generic class Illuminate\\\\Database\\\\Eloquent\\\\Builder but does not specify its types\\: TModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:scopeOwnedBy\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Builder does not specify its types\\: TModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:user\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Restaurant\\:\\:visits\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\HasMany does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Restaurant.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\User\\:\\:partner\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/User.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\User\\:\\:restaurants\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\HasMany does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/User.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\User\\:\\:visits\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\HasMany does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/User.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Visit\\:\\:restaurant\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Visit.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Models\\\\Visit\\:\\:user\\(\\) return type with generic class Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo does not specify its types\\: TRelatedModel, TDeclaringModel$#',
	'identifier' => 'missingType.generics',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/Visit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property Illuminate\\\\Database\\\\Eloquent\\\\Model\\:\\:\\$owner_user_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/app/Policies/EventPolicy.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property Illuminate\\\\Database\\\\Eloquent\\\\Model\\:\\:\\$preferred_vibe_tags\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\PatioQuality\\:\\:Decent is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\PatioQuality\\:\\:Destination is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\PatioQuality\\:\\:None is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match expression does not handle remaining value\\: string$#',
	'identifier' => 'match.unhandled',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Services\\\\QuickPickService\\:\\:pickFromTop\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$haystack of function in_array expects array, array\\|string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\.\\.\\.\\$arrays of function array_intersect expects array, array\\|string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuickPickService.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between string and App\\\\Enums\\\\RestaurantSource\\:\\:Places will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
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
	'message' => '#^Access to an undefined property Illuminate\\\\Database\\\\Eloquent\\\\Model\\:\\:\\$preferred_vibe_tags\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function in_array\\(\\) with arguments string\\|null, array\\{App\\\\Enums\\\\ServiceLevel\\:\\:Casual\\}\\|array\\{App\\\\Enums\\\\ServiceLevel\\:\\:FastFood, App\\\\Enums\\\\ServiceLevel\\:\\:FastCasual\\}\\|array\\{App\\\\Enums\\\\ServiceLevel\\:\\:FineDining\\}\\|array\\{App\\\\Enums\\\\ServiceLevel\\:\\:UpscaleCasual\\} and true will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$value on string\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\PatioQuality\\:\\:Decent is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\PatioQuality\\:\\:Destination is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\PatioQuality\\:\\:None is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match expression does not handle remaining value\\: string$#',
	'identifier' => 'match.unhandled',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$haystack of function in_array expects array, array\\|string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\.\\.\\.\\$arrays of function array_intersect expects array, array\\|string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Using nullsafe property access "\\?\\-\\>preferred_vibe_tags" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
	'identifier' => 'nullsafe.neverNull',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/QuizService.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method toDateString\\(\\) on string\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\EventRecurrence\\:\\:Monthly is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\EventRecurrence\\:\\:OneOff is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match arm comparison between string and App\\\\Enums\\\\EventRecurrence\\:\\:Weekly is always false\\.$#',
	'identifier' => 'match.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
];
$ignoreErrors[] = [
	'message' => '#^Match expression does not handle remaining value\\: string$#',
	'identifier' => 'match.unhandled',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$callback of method Illuminate\\\\Support\\\\Collection\\<int,Illuminate\\\\Database\\\\Eloquent\\\\Model\\>\\:\\:filter\\(\\) expects \\(callable\\(Illuminate\\\\Database\\\\Eloquent\\\\Model, int\\)\\: bool\\)\\|null, Closure\\(App\\\\Models\\\\Event\\)\\: bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$events of method App\\\\Services\\\\TonightService\\:\\:hasQualifyingEvent\\(\\) expects Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Event\\>, Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, Illuminate\\\\Database\\\\Eloquent\\\\Model\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/TonightService.php',
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
