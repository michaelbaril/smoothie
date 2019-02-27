<?php

use Faker\Generator as Faker;

$factory->define(Baril\Smoothie\Tests\Models\Article::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(3),
        'body' => $faker->text(50),
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Paragraph::class, function (Faker $faker) {
    return [
        'content' => $faker->text(20),
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Tag::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->word,
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Status::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->word,
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Post::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(3),
        'body' => $faker->text(50),
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Video::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(3),
        'url' => $faker->url,
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Country::class, function (Faker $faker) {
    return [
        'code' => strtoupper(substr($faker->word, 0, 2)),
        'name' => $faker->word,
    ];
});

$factory->define(Baril\Smoothie\Tests\Models\Person::class, function (Faker $faker) {
    $country = Baril\Smoothie\Tests\Models\Country::all()->random();
    return [
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'birth_country_id' => $country ? $country->id : null,
    ];
});