<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /** Coverage types are reference data every environment needs; the demo data is for development only. */
    public function run(): void
    {
        if (DB::table('coverage_types')->doesntExist()) {
            DB::unprepared(file_get_contents(__DIR__.'/sql/coverage_types.sql'));
        }

        if (app()->isProduction()) {
            return;
        }
        if (DB::table('plans')->exists()) {
            throw new RuntimeException('Refusing to load demo data: plans already contains data');
        }
        DB::unprepared(file_get_contents(__DIR__.'/sql/demo.sql'));
    }
}
