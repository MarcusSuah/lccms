<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production receives roles and permissions only. Development fixtures are
     * labelled DEV- and are never official administrative data.
     *
     * Model events stay enabled: geographic models derive normalized names
     * and slugs in their saving hooks, and seeded records should be audited.
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);

        if (! app()->isProduction()) {
            $this->call(DevelopmentFixturesSeeder::class);
        }
    }
}
