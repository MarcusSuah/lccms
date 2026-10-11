<?php

namespace Database\Seeders;

use App\Enums\Facilities\FacilityCategory;
use App\Enums\Leadership\LeadershipLevel;
use App\Models\Facilities\FacilityType;
use App\Models\Leadership\LeadershipPosition;
use Illuminate\Database\Seeder;

/**
 * Generic, configurable reference lists (facility types and leadership titles).
 * These are starting points administrators can rename or extend; they are not
 * official statistics or administrative records. Idempotent.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['early_childhood_school', 'Early childhood education centre', FacilityCategory::School],
            ['primary_school', 'Primary school', FacilityCategory::School],
            ['junior_high_school', 'Junior high school', FacilityCategory::School],
            ['senior_high_school', 'Senior high school', FacilityCategory::School],
            ['vocational_school', 'Vocational / technical school', FacilityCategory::School],
            ['hospital', 'Hospital', FacilityCategory::Hospital],
            ['health_centre', 'Health centre', FacilityCategory::Clinic],
            ['clinic', 'Clinic', FacilityCategory::Clinic],
            ['health_post', 'Health post', FacilityCategory::OtherHealth],
            ['pharmacy', 'Pharmacy / medicine store', FacilityCategory::OtherHealth],
            ['market', 'Market', FacilityCategory::Market],
            ['public_toilet', 'Public toilet', FacilityCategory::Sanitation],
            ['community_hall', 'Community hall', FacilityCategory::OtherService],
            ['police_depot', 'Police depot', FacilityCategory::OtherService],
        ];

        foreach ($types as $index => [$code, $name, $category]) {
            FacilityType::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'sort_order' => $index,
            ]);
        }

        $positions = [
            ['community_chairperson', 'Community chairperson', LeadershipLevel::Community, true],
            ['town_chief', 'Town chief', LeadershipLevel::Community, true],
            ['secretary', 'Secretary', LeadershipLevel::Community, true],
            ['treasurer', 'Treasurer', LeadershipLevel::Community, true],
            ['women_leader', "Women's leader", LeadershipLevel::Community, true],
            ['youth_leader', 'Youth leader', LeadershipLevel::Community, true],
            ['block_leader', 'Block leader', LeadershipLevel::Block, true],
        ];

        foreach ($positions as $index => [$code, $title, $level, $single]) {
            LeadershipPosition::query()->firstOrCreate(['code' => $code], [
                'title' => $title,
                'level' => $level,
                'single_holder' => $single,
                'sort_order' => $index,
            ]);
        }
    }
}
