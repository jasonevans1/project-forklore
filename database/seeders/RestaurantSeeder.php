<?php

namespace Database\Seeders;

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password')],
        );

        $restaurants = [
            [
                'name' => "Fong's Pizza",
                'address' => '223 4th St, Des Moines',
                'cuisine_tags' => ['pizza'],
                'vibe_tags' => ['lively'],
                'price_level' => 2,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::Decent,
                'indoor_vibe_when_cold' => IndoorVibe::Neutral,
                'service_level' => ServiceLevel::Casual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value, ServiceOption::Delivery->value],
                'primary_cuisine' => PrimaryCuisine::Pizza,
            ],
            [
                'name' => 'Exile Brewing Co',
                'address' => '1514 Walnut St, Des Moines',
                'cuisine_tags' => ['american'],
                'vibe_tags' => ['casual'],
                'price_level' => 2,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::Decent,
                'indoor_vibe_when_cold' => IndoorVibe::Neutral,
                'service_level' => ServiceLevel::Casual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::American,
            ],
            [
                'name' => 'Centro',
                'address' => '1003 Locust St, Des Moines',
                'cuisine_tags' => ['italian'],
                'vibe_tags' => ['date_night'],
                'price_level' => 3,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::None,
                'indoor_vibe_when_cold' => IndoorVibe::Cozy,
                'service_level' => ServiceLevel::UpscaleCasual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::Italian,
            ],
            [
                'name' => 'Zombie Burger',
                'address' => '300 E Grand Ave, Des Moines',
                'cuisine_tags' => ['burgers'],
                'vibe_tags' => ['casual'],
                'price_level' => 1,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::None,
                'indoor_vibe_when_cold' => IndoorVibe::Neutral,
                'service_level' => ServiceLevel::FastCasual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value, ServiceOption::Delivery->value],
                'primary_cuisine' => PrimaryCuisine::American,
            ],
            [
                'name' => 'Django',
                'address' => '1420 Locust St, Des Moines',
                'cuisine_tags' => ['french', 'american'],
                'vibe_tags' => ['date_night', 'cozy'],
                'price_level' => 3,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::None,
                'indoor_vibe_when_cold' => IndoorVibe::Cozy,
                'service_level' => ServiceLevel::UpscaleCasual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::Other,
            ],
            [
                'name' => 'Proof',
                'address' => '1401 Locust St, Des Moines',
                'cuisine_tags' => ['american'],
                'vibe_tags' => ['date_night'],
                'price_level' => 3,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::Destination,
                'indoor_vibe_when_cold' => IndoorVibe::Cozy,
                'service_level' => ServiceLevel::FineDining,
                'service_options' => [ServiceOption::DineIn->value],
                'primary_cuisine' => PrimaryCuisine::Mediterranean,
            ],
            [
                'name' => 'ARC Restaurant',
                'address' => '1901 Bell Ave Ste 111, Des Moines',
                'cuisine_tags' => ['american'],
                'vibe_tags' => ['trendy'],
                'price_level' => 3,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::Decent,
                'indoor_vibe_when_cold' => IndoorVibe::Neutral,
                'service_level' => ServiceLevel::UpscaleCasual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::American,
            ],
            [
                'name' => 'El Bait Shop',
                'address' => '200 SW 2nd St, Des Moines',
                'cuisine_tags' => ['american', 'burgers'],
                'vibe_tags' => ['casual'],
                'price_level' => 1,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::Decent,
                'indoor_vibe_when_cold' => IndoorVibe::Neutral,
                'service_level' => ServiceLevel::Casual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::BarFood,
            ],
            [
                'name' => "Zanzibar's Coffee Adventure",
                'address' => '2723 Ingersoll Ave, Des Moines',
                'cuisine_tags' => ['café'],
                'vibe_tags' => ['casual'],
                'price_level' => 1,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::None,
                'indoor_vibe_when_cold' => IndoorVibe::Cozy,
                'service_level' => ServiceLevel::FastCasual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::Cafe,
            ],
            [
                'name' => 'Eatery A',
                'address' => '600 Keosauqua Way, Des Moines',
                'cuisine_tags' => ['asian fusion'],
                'vibe_tags' => ['date_night'],
                'price_level' => 2,
                'source' => RestaurantSource::Favorite,
                'patio_quality' => PatioQuality::None,
                'indoor_vibe_when_cold' => IndoorVibe::Neutral,
                'service_level' => ServiceLevel::UpscaleCasual,
                'service_options' => [ServiceOption::DineIn->value, ServiceOption::Takeout->value],
                'primary_cuisine' => PrimaryCuisine::AsianGeneral,
            ],
        ];

        foreach ($restaurants as $data) {
            Restaurant::firstOrCreate(
                ['owner_user_id' => $owner->id, 'name' => $data['name']],
                array_merge($data, [
                    'lat' => 41.58,
                    'lng' => -93.62,
                ]),
            );
        }
    }
}
