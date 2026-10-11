<?php

namespace Database\Seeders;

use App\Enums\Access\RoleName;
use App\Models\Geography\Block;
use App\Models\Geography\Clan;
use App\Models\Geography\Community;
use App\Models\Geography\County;
use App\Models\Geography\District;
use App\Models\Geography\Town;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Clearly labelled development data. Every geographic record carries the DEV-
 * reference prefix and is_fixture = true. These are NOT official Liberian
 * administrative records; real data must arrive through the import workflow.
 */
class DevelopmentFixturesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Development fixtures must never be seeded in production.');
        }

        $county = County::query()->firstOrCreate(
            ['reference_code' => 'DEV-CO01'],
            ['name' => 'Fixture County Alpha', 'is_fixture' => true],
        );

        $district = District::query()->firstOrCreate(
            ['reference_code' => 'DEV-CO01-D01'],
            ['county_id' => $county->id, 'district_number' => 1, 'name' => 'District #1', 'is_fixture' => true],
        );

        $clan = Clan::query()->firstOrCreate(
            ['reference_code' => 'DEV-CO01-D01-CL01'],
            [...$district->pathAttributes(), 'name' => 'Fixture Clan One', 'is_fixture' => true],
        );

        $town = Town::query()->firstOrCreate(
            ['reference_code' => 'DEV-CO01-D01-CL01-T01'],
            [...$clan->pathAttributes(), 'name' => 'Fixture Town One', 'is_fixture' => true],
        );

        $community = Community::query()->firstOrCreate(
            ['reference_code' => 'DEV-CO01-D01-CL01-T01-C01'],
            [
                ...$town->pathAttributes(),
                'name' => 'Fixture Community One',
                'description' => 'Development fixture community. Not an official record.',
                'is_fixture' => true,
                'is_published' => true,
            ],
        );

        foreach (range(1, 3) as $number) {
            Block::query()->firstOrCreate(
                ['reference_code' => sprintf('DEV-CO01-D01-CL01-T01-C01-B%02d', $number)],
                [...$community->pathAttributes(), 'name' => "Fixture Block {$number}", 'is_fixture' => true],
            );
        }

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'admin@lccms.test'],
            ['name' => 'Development Super Administrator', 'password' => 'password'],
        );
        $superAdmin->forceFill(['email_verified_at' => now()])->save();
        $superAdmin->syncRoles([RoleName::SystemSuperAdministrator->value]);

        $enumerator = User::query()->firstOrCreate(
            ['email' => 'enumerator@lccms.test'],
            ['name' => 'Development Enumerator', 'password' => 'password'],
        );
        $enumerator->forceFill(['email_verified_at' => now()])->save();
        $enumerator->syncRoles([RoleName::CensusEnumerator->value]);
        $enumerator->geoScopes()->firstOrCreate($community->pathAttributes());

        $supervisor = User::query()->firstOrCreate(
            ['email' => 'supervisor@lccms.test'],
            ['name' => 'Development Supervisor', 'password' => 'password'],
        );
        $supervisor->forceFill(['email_verified_at' => now()])->save();
        $supervisor->syncRoles([RoleName::CensusSupervisor->value]);
        $supervisor->geoScopes()->firstOrCreate($town->pathAttributes());

        $countyAdmin = User::query()->firstOrCreate(
            ['email' => 'county@lccms.test'],
            ['name' => 'Development County Administrator', 'password' => 'password'],
        );
        $countyAdmin->forceFill(['email_verified_at' => now()])->save();
        $countyAdmin->syncRoles([RoleName::CountyAdministrator->value]);
        $countyAdmin->geoScopes()->firstOrCreate($county->pathAttributes());
    }
}
