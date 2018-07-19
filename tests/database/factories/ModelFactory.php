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