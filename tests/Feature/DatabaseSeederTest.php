<?php

use App\Models\Geography\Community;
use App\Models\Geography\County;
use Database\Seeders\DatabaseSeeder;

test('the database seeder creates labelled fixtures with derived names and slugs', function () {
    $this->seed(DatabaseSeeder::class);

    $county = County::query()->where('reference_code', 'DEV-CO01')->sole();
    $community = Community::query()->where('reference_code', 'DEV-CO01-D01-CL01-T01-C01')->sole();

    expect($county->is_fixture)->toBeTrue()
        ->and($county->normalized_name)->toBe(County::normalizeName('Fixture County Alpha'))
        ->and($community->slug)->not->toBeEmpty();
});

test('seeding twice does not duplicate fixtures', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(County::query()->where('reference_code', 'DEV-CO01')->count())->toBe(1);
});
