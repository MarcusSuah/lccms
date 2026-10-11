<?php

use App\Actions\Census\CensusWorkflow;
use App\Actions\Census\DecideCorrection;
use App\Actions\Census\RegisterHouse;
use App\Actions\Census\RequestCorrection;
use App\Actions\Census\SaveHousehold;
use App\Actions\Census\SaveOccupant;
use App\Actions\Geography\ApplyGeoReassignment;
use App\Enums\Access\RoleName;
use App\Enums\Census\CampaignStatus;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\CorrectionStatus;
use App\Enums\Geography\GeoLevel;
use App\Exceptions\CensusActionException;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\Census\DuplicateFlag;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Models\Geography\GeoReassignment;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusStatistics;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * An active campaign over a county, with an enumerator assigned to the
 * community, a supervisor for the town and a county administrator.
 *
 * @return array<string, mixed>
 */
function censusScenario(): array
{
    $chain = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    $enumerator = staffUser(RoleName::CensusEnumerator);
    $supervisor = staffUser(RoleName::CensusSupervisor);
    $countyAdmin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    CampaignAssignment::factory()->for($campaign, 'campaign')->for($enumerator)->inArea($chain['community'])->create(['supervisor_id' => $supervisor->id]);
    CampaignAssignment::factory()->for($campaign, 'campaign')->for($supervisor)->supervisor()->inArea($chain['town'])->create();

    return [...$chain, 'campaign' => $campaign, 'enumerator' => $enumerator, 'supervisor' => $supervisor, 'countyAdmin' => $countyAdmin];
}

/**
 * @return array{0: Household, 1: Occupant}
 */
function enumeratedHousehold(array $s, string $headName = 'Musu Kollie'): array
{
    $house = app(RegisterHouse::class)->create($s['enumerator'], $s['campaign'], $s['block'], ['local_house_number' => (string) random_int(1, 99999)]);
    $household = app(SaveHousehold::class)->create($s['enumerator'], $s['campaign'], $house, ['residence_type' => 'usual']);
    $head = app(SaveOccupant::class)->create($s['enumerator'], $household, [
        'full_name' => $headName, 'age_years' => 41, 'sex' => 'female', 'relationship_to_head' => 'head', 'usual_resident' => true,
    ]);

    return [$household, $head];
}

test('one house holds several households, each with several individual occupants', function () {
    $s = censusScenario();
    $house = app(RegisterHouse::class)->create($s['enumerator'], $s['campaign'], $s['block'], ['local_house_number' => '12A']);

    $first = app(SaveHousehold::class)->create($s['enumerator'], $s['campaign'], $house, ['residence_type' => 'usual']);
    $second = app(SaveHousehold::class)->create($s['enumerator'], $s['campaign'], $house, ['residence_type' => 'usual']);

    foreach ([$first, $second] as $household) {
        app(SaveOccupant::class)->create($s['enumerator'], $household, ['full_name' => fake()->name(), 'age_years' => 40, 'sex' => 'male', 'relationship_to_head' => 'head', 'usual_resident' => true]);
        app(SaveOccupant::class)->create($s['enumerator'], $household, ['full_name' => fake()->name(), 'age_years' => 9, 'sex' => 'female', 'relationship_to_head' => 'child', 'usual_resident' => true]);
    }

    expect($house->households()->count())->toBe(2)
        ->and($first->sequence_in_house)->toBe(1)
        ->and($second->sequence_in_house)->toBe(2)
        ->and($first->occupants()->count())->toBe(2)
        ->and(Occupant::query()->where('campaign_id', $s['campaign']->id)->count())->toBe(4)
        ->and($second->occupants()->first()->block_id)->toBe($s['block']->id)
        ->and($house->reference_code)->toStartWith($s['block']->reference_code.'-H');
});

test('enumerators cannot record houses outside their assignment', function () {
    $s = censusScenario();
    $otherBlock = Block::factory()->create();

    app(RegisterHouse::class)->create($s['enumerator'], $s['campaign'], $otherBlock, []);
})->throws(CensusActionException::class, 'You are not assigned to this area for this campaign.');

test('field data is refused unless the campaign is active', function (CampaignStatus $status) {
    $s = censusScenario();
    $s['campaign']->forceFill(['status' => $status])->save();

    app(RegisterHouse::class)->create($s['enumerator'], $s['campaign']->fresh(), $s['block'], []);
})->throws(CensusActionException::class)->with([
    'draft' => [CampaignStatus::Draft],
    'in review' => [CampaignStatus::InReview],
    'closed' => [CampaignStatus::Closed],
]);

test('a household cannot have two heads', function () {
    $s = censusScenario();
    [$household] = enumeratedHousehold($s);

    expect(fn () => app(SaveOccupant::class)->create($s['enumerator'], $household, [
        'full_name' => 'Second Head', 'age_years' => 50, 'sex' => 'male', 'relationship_to_head' => 'head', 'usual_resident' => true,
    ]))->toThrow(ValidationException::class, 'This household already has a head.');
});

test('a household without a head cannot be submitted', function () {
    $s = censusScenario();
    $house = app(RegisterHouse::class)->create($s['enumerator'], $s['campaign'], $s['block'], []);
    $household = app(SaveHousehold::class)->create($s['enumerator'], $s['campaign'], $house, ['residence_type' => 'usual']);
    app(SaveOccupant::class)->create($s['enumerator'], $household, ['full_name' => 'Only Child', 'age_years' => 7, 'sex' => 'male', 'relationship_to_head' => 'child', 'usual_resident' => true]);

    app(CensusWorkflow::class)->submit($s['enumerator'], $household);
})->throws(CensusActionException::class, 'Exactly one occupant must be recorded as head of household.');

test('approved totals change only after supervisor verification and administrator approval', function () {
    $s = censusScenario();
    [$household] = enumeratedHousehold($s);
    app(SaveOccupant::class)->create($s['enumerator'], $household, ['full_name' => 'Junior Kollie', 'age_years' => 6, 'sex' => 'male', 'relationship_to_head' => 'child', 'usual_resident' => true]);
    $statistics = app(CensusStatistics::class);
    $workflow = app(CensusWorkflow::class);

    $workflow->submit($s['enumerator'], $household);
    expect($statistics->summary($s['campaign'], null)['occupants'])->toMatchArray(['registered' => 2, 'submitted' => 2, 'approved' => 0]);

    $workflow->verify($s['supervisor'], $household->fresh());
    expect($statistics->summary($s['campaign'], null)['occupants'])->toMatchArray(['verified' => 2, 'approved' => 0]);

    $workflow->approve($s['countyAdmin'], $household->fresh());
    $summary = $statistics->summary($s['campaign'], null);

    expect($summary['occupants']['approved'])->toBe(2)
        ->and($summary['adults'])->toBe(1)
        ->and($summary['children'])->toBe(1)
        ->and($summary['sex'])->toEqual(['female' => 1, 'male' => 1])
        ->and($summary['households']['approved'])->toBe(1)
        ->and($summary['houses']['approved'])->toBe(1)
        ->and($summary['average_household_size'])->toBe(2.0)
        ->and($household->reviews()->pluck('action')->all())->toBe(['submit', 'verify', 'approve']);
});

test('supervisors cannot give final approval and enumerators cannot approve their own work', function () {
    $s = censusScenario();
    [$household] = enumeratedHousehold($s);
    $workflow = app(CensusWorkflow::class);
    $workflow->submit($s['enumerator'], $household);
    $workflow->verify($s['supervisor'], $household->fresh());

    expect(fn () => $workflow->approve($s['supervisor'], $household->fresh()))
        ->toThrow(CensusActionException::class, 'You cannot approve records in this area.');
});

test('a returned household can be fixed by its enumerator but an approved one cannot', function () {
    $s = censusScenario();
    [$household, $head] = enumeratedHousehold($s);
    $workflow = app(CensusWorkflow::class);

    $workflow->submit($s['enumerator'], $household);
    $workflow->reject($s['supervisor'], $household->fresh(), 'Head age looks wrong');

    app(SaveOccupant::class)->update($s['enumerator'], $head->fresh(), ['age_years' => 44]);
    expect($head->fresh()->age_years)->toBe(44)
        ->and($head->fresh()->status)->toBe(CensusRecordStatus::Rejected);

    $workflow->submit($s['enumerator'], $household->fresh());
    $workflow->verify($s['supervisor'], $household->fresh());
    $workflow->approve($s['countyAdmin'], $household->fresh());

    expect(fn () => app(SaveOccupant::class)->update($s['enumerator'], $head->fresh(), ['age_years' => 45]))
        ->toThrow(CensusActionException::class, 'can only be changed through a correction request');
});

test('corrections to approved records need a second person and are recorded', function () {
    $s = censusScenario();
    [$household, $head] = enumeratedHousehold($s);
    $workflow = app(CensusWorkflow::class);
    $workflow->submit($s['enumerator'], $household);
    $workflow->verify($s['supervisor'], $household->fresh());
    $workflow->approve($s['countyAdmin'], $household->fresh());
    $versionBefore = $head->fresh()->version;
    $secondAdmin = staffUser(RoleName::CountyAdministrator, $s['county']);

    $correction = app(RequestCorrection::class)->handle($s['supervisor'], $head->fresh(), ['age_years' => 43], 'Birth certificate shown');

    expect(fn () => app(DecideCorrection::class)->approve($s['supervisor'], $correction, null))
        ->toThrow(CensusActionException::class);

    app(DecideCorrection::class)->approve($secondAdmin, $correction, 'Checked');

    expect($head->fresh()->age_years)->toBe(43)
        ->and($head->fresh()->version)->toBeGreaterThan($versionBefore)
        ->and($head->fresh()->status)->toBe(CensusRecordStatus::Approved)
        ->and($correction->fresh()->status)->toBe(CorrectionStatus::Approved)
        ->and($correction->fresh()->changes)->toBe(['age_years' => ['from' => 41, 'to' => 43]]);
});

test('a new campaign never overwrites the records of a previous one', function () {
    $s = censusScenario();
    [$household] = enumeratedHousehold($s);
    $workflow = app(CensusWorkflow::class);
    $workflow->submit($s['enumerator'], $household);
    $workflow->verify($s['supervisor'], $household->fresh());
    $workflow->approve($s['countyAdmin'], $household->fresh());
    $s['campaign']->forceFill(['status' => CampaignStatus::Closed])->save();

    $next = CensusCampaign::factory()->active()->covering($s['county'])->create();
    CampaignAssignment::factory()->for($next, 'campaign')->for($s['enumerator'])->inArea($s['community'])->create();
    app(CensusAccess::class)->forget($s['enumerator']);
    $house = $household->house;

    $revisit = app(SaveHousehold::class)->create($s['enumerator'], $next, $house, ['residence_type' => 'usual']);

    expect($revisit->id)->not->toBe($household->id)
        ->and($revisit->sequence_in_house)->toBe(1)
        ->and(app(CensusStatistics::class)->summary($s['campaign'], null)['occupants']['approved'])->toBe(1)
        ->and(app(CensusStatistics::class)->summary($next, null)['occupants']['registered'])->toBe(0);
});

test('closed campaign results keep their original location after a geographic reassignment', function () {
    $s = censusScenario();
    [$household, $head] = enumeratedHousehold($s);
    $s['campaign']->forceFill(['status' => CampaignStatus::Closed])->save();
    $destination = Community::factory()->create();
    $mover = staffUser(RoleName::NationalCensusAdministrator);
    $approver = staffUser(RoleName::NationalCensusAdministrator);

    $reassignment = GeoReassignment::query()->create([
        'level' => GeoLevel::Block, 'area_id' => $s['block']->id, 'area_reference_code' => $s['block']->reference_code,
        'area_name' => $s['block']->name, 'from_parent_id' => $s['community']->id, 'to_parent_id' => $destination->id,
        'from_path' => [], 'to_path' => [], 'reason' => 'Survey correction', 'effective_on' => now(), 'requested_by' => $mover->id,
    ]);
    app(ApplyGeoReassignment::class)->approve($reassignment, $approver, null);

    expect($household->house->fresh()->community_id)->toBe($destination->id)
        ->and($household->fresh()->community_id)->toBe($s['community']->id)
        ->and($head->fresh()->community_id)->toBe($s['community']->id);
});

test('possible duplicate registrations are flagged for review, not merged', function () {
    $s = censusScenario();
    [, $first] = enumeratedHousehold($s, 'Musu Kollie');
    [, $second] = enumeratedHousehold($s, 'Musu Kollie');

    $flag = DuplicateFlag::query()->sole();

    expect($flag->occupant_id)->toBe($second->id)
        ->and($flag->matched_occupant_id)->toBe($first->id)
        ->and($flag->reasons)->toContain('Same name')
        ->and(Occupant::query()->count())->toBe(2);
});

test('breakdowns distinguish areas never enumerated from areas with zero people', function () {
    $s = censusScenario();
    $emptyBlock = Block::factory()->create([...$s['community']->pathAttributes()]);
    enumeratedHousehold($s);

    $rows = collect(app(CensusStatistics::class)->breakdown($s['campaign'], null, GeoLevel::Block, ['community_id' => $s['community']->id]))->keyBy('id');

    expect($rows[$s['block']->id]['is_enumerated'])->toBeTrue()
        ->and($rows[$s['block']->id]['occupants']['registered'])->toBe(1)
        ->and($rows[$emptyBlock->id]['is_enumerated'])->toBeFalse()
        ->and($rows[$emptyBlock->id]['occupants']['registered'])->toBe(0);
});

test('personal data never reaches the activity log', function () {
    $s = censusScenario();
    enumeratedHousehold($s, 'Secret Person Name');

    $logged = Activity::query()->where('subject_type', (new Occupant)->getMorphClass())->get();

    expect($logged)->not->toBeEmpty()
        ->and($logged->pluck('attribute_changes')->toJson())->not->toContain('Secret Person Name')
        ->and($logged->first()->attribute_changes['attributes']['full_name'])->toBe('[redacted]');
});
