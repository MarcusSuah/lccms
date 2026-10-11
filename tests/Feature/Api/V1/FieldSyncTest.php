<?php

use App\Actions\Census\CensusWorkflow;
use App\Actions\Sync\RegisterDevice;
use App\Enums\Access\RoleName;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Sync\ConflictStatus;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Geography\Block;
use App\Models\Sync\SyncConflict;
use App\Models\User;
use App\Services\Census\CensusStatistics;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function fieldScenario(): array
{
    $chain = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    $enumerator = staffUser(RoleName::CensusEnumerator);
    CampaignAssignment::factory()->for($campaign, 'campaign')->for($enumerator)->inArea($chain['community'])->create();
    ['device' => $device, 'token' => $token] = app(RegisterDevice::class)->handle($enumerator, 'Tablet 7', 'test');

    return [...$chain, 'campaign' => $campaign, 'enumerator' => $enumerator, 'device' => $device, 'token' => $token];
}

/**
 * @param  list<array<string, mixed>>  $operations
 * @return array<string, mixed>
 */
function batch(array $s, array $operations, ?string $batchUuid = null): array
{
    return [
        'batch_uuid' => $batchUuid ?? (string) Str::uuid(),
        'campaign_id' => $s['campaign']->id,
        'operations' => $operations,
    ];
}

function op(string $entityType, string $operation, string $entityUuid, array $payload = [], ?int $baseVersion = null): array
{
    return array_filter([
        'op_uuid' => (string) Str::uuid(),
        'entity_type' => $entityType,
        'entity_uuid' => $entityUuid,
        'operation' => $operation,
        'base_version' => $baseVersion,
        'payload' => $payload,
    ], fn ($value) => $value !== null);
}

/**
 * An offline house with two households of two people each, in dependency order.
 *
 * @return list<array<string, mixed>>
 */
function offlineHouseOperations(array $s): array
{
    $house = (string) Str::uuid();
    $operations = [op('house', 'create', $house, ['block_id' => $s['block']->id, 'local_house_number' => '44', 'building_type' => 'compound'])];

    foreach (['Kollie', 'Doe'] as $family) {
        $household = (string) Str::uuid();
        $operations[] = op('household', 'create', $household, ['house_uuid' => $house, 'residence_type' => 'usual']);
        $operations[] = op('occupant', 'create', (string) Str::uuid(), ['household_uuid' => $household, 'full_name' => "Head {$family}", 'age_years' => 45, 'sex' => 'female', 'relationship_to_head' => 'head', 'usual_resident' => true]);
        $operations[] = op('occupant', 'create', (string) Str::uuid(), ['household_uuid' => $household, 'full_name' => "Child {$family}", 'age_years' => 8, 'sex' => 'male', 'relationship_to_head' => 'child', 'usual_resident' => true, 'school_attendance' => 'currently']);
        $operations[] = op('household', 'submit', $household, [], 1);
    }

    return $operations;
}

test('a device is registered from an online session and receives a sync token', function () {
    $s = fieldScenario();

    $this->actingAs($s['enumerator'])
        ->postJson(route('field.devices.store'), ['label' => 'Second tablet'])
        ->assertCreated()
        ->assertJsonStructure(['device_uuid', 'token', 'offline_expires_at']);
});

test('roles without field work cannot register devices', function () {
    $editor = staffUser(RoleName::ContentEditor);

    $this->actingAs($editor)->postJson(route('field.devices.store'), ['label' => 'Laptop'])->assertUnprocessable();
});

test('an offline house with several households and occupants synchronises in one batch', function () {
    $s = fieldScenario();

    $response = $this->withToken($s['token'])
        ->postJson(route('api.v1.sync.batches.store'), batch($s, offlineHouseOperations($s)))
        ->assertOk();

    expect(collect($response->json('results'))->pluck('status')->unique()->all())->toBe(['applied'])
        ->and(House::query()->count())->toBe(1)
        ->and(Household::query()->count())->toBe(2)
        ->and(Occupant::query()->count())->toBe(4)
        ->and(Household::query()->pluck('status')->unique()->all())->toBe([CensusRecordStatus::Submitted])
        ->and($response->json('results.0.record.reference_code'))->toStartWith($s['block']->reference_code.'-H');
});

test('retrying the same batch, or the same operations in a new batch, creates no duplicates', function () {
    $s = fieldScenario();
    $payload = batch($s, offlineHouseOperations($s));

    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), $payload)->assertOk();
    $retry = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), $payload)->assertOk();
    $rebuilt = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), [...$payload, 'batch_uuid' => (string) Str::uuid()])->assertOk();

    expect(House::query()->count())->toBe(1)
        ->and(Household::query()->count())->toBe(2)
        ->and(Occupant::query()->count())->toBe(4)
        ->and($retry->json('results.0.replayed'))->toBeTrue()
        ->and($rebuilt->json('results.0.status'))->toBe('applied')
        ->and($rebuilt->json('results.0.replayed'))->toBeTrue();
});

test('a new operation that recreates an existing record returns the existing record', function () {
    $s = fieldScenario();
    $house = (string) Str::uuid();

    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [op('house', 'create', $house, ['block_id' => $s['block']->id])]));
    $second = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [op('house', 'create', $house, ['block_id' => $s['block']->id])]));

    expect($second->json('results.0.status'))->toBe('duplicate')
        ->and(House::query()->count())->toBe(1);
});

test('an operation id reused for different data is refused', function () {
    $s = fieldScenario();
    $operation = op('house', 'create', (string) Str::uuid(), ['block_id' => $s['block']->id, 'local_house_number' => '1']);

    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [$operation]));
    $tampered = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [[...$operation, 'payload' => ['block_id' => $s['block']->id, 'local_house_number' => '2']]]));

    expect($tampered->json('results.0.code'))->toBe('op_uuid_reused')
        ->and(House::query()->sole()->local_house_number)->toBe('1');
});

test('invalid and unauthorised operations are rejected with actionable errors without blocking the rest', function () {
    $s = fieldScenario();
    $foreignBlock = Block::factory()->create();
    $household = (string) Str::uuid();

    $response = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('house', 'create', (string) Str::uuid(), ['block_id' => $foreignBlock->id]),
        op('house', 'create', (string) Str::uuid(), ['block_id' => $s['block']->id, 'floors' => 400]),
        op('occupant', 'create', (string) Str::uuid(), ['household_id' => 999999, 'full_name' => 'X', 'sex' => 'female', 'relationship_to_head' => 'head', 'usual_resident' => true]),
        op('house', 'create', (string) Str::uuid(), ['block_id' => $s['block']->id]),
    ]))->assertOk();

    $results = $response->json('results');

    expect($results[0]['status'])->toBe('rejected')
        ->and($results[0]['code'])->toBe('unauthorized')
        ->and($results[1]['code'])->toBe('validation_failed')
        ->and($results[1]['errors'])->toHaveKey('floors')
        ->and($results[2]['code'])->toBe('validation_failed')
        ->and($results[3]['status'])->toBe('applied')
        ->and(House::query()->count())->toBe(1);
});

test('an enumerator cannot change another enumerator\'s records', function () {
    $s = fieldScenario();
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, offlineHouseOperations($s)));
    $household = Household::query()->first();

    $intruder = staffUser(RoleName::CensusEnumerator);
    CampaignAssignment::factory()->for($s['campaign'], 'campaign')->for($intruder)->inArea($s['community'])->create();
    ['token' => $intruderToken] = app(RegisterDevice::class)->handle($intruder, 'Other tablet', 'test');

    app('auth')->forgetGuards();

    $response = $this->withToken($intruderToken)->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('household', 'update', $household->uuid, ['residence_type' => 'temporary'], $household->version),
    ]));

    expect($response->json('results.0.code'))->toBe('unauthorized')
        ->and($household->fresh()->residence_type->value)->toBe('usual');
});

test('a child arriving before its parent is retryable and succeeds once the parent syncs', function () {
    $s = fieldScenario();
    $house = (string) Str::uuid();
    $householdOp = op('household', 'create', (string) Str::uuid(), ['house_uuid' => $house, 'residence_type' => 'usual']);

    $first = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [$householdOp]));
    expect($first->json('results.0.status'))->toBe('retryable')
        ->and($first->json('results.0.code'))->toBe('parent_pending');

    $second = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('house', 'create', $house, ['block_id' => $s['block']->id]),
        $householdOp,
    ]));

    expect($second->json('results.1.status'))->toBe('applied')
        ->and(Household::query()->count())->toBe(1);
});

test('an edit to a record reviewed in the meantime is preserved as a conflict, not applied', function () {
    $s = fieldScenario();
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, offlineHouseOperations($s)));
    $occupant = Occupant::query()->where('full_name', 'Child Kollie')->firstOrFail();
    $staleVersion = $occupant->version;
    $supervisor = staffUser(RoleName::CensusSupervisor, $s['town']);
    app(CensusWorkflow::class)->verify($supervisor, $occupant->household);

    $response = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('occupant', 'update', $occupant->uuid, ['full_name' => 'Child Kollie Jr', 'age_years' => 9, 'sex' => 'male', 'relationship_to_head' => 'child', 'usual_resident' => true], $staleVersion),
    ]));

    $conflict = SyncConflict::query()->sole();

    expect($response->json('results.0.status'))->toBe('conflict')
        ->and($response->json('results.0.code'))->toBe('edit_after_review')
        ->and($occupant->fresh()->full_name)->toBe('Child Kollie')
        ->and($conflict->submitted_payload['full_name'])->toBe('Child Kollie Jr')
        ->and($conflict->server_snapshot['full_name'])->toBe('Child Kollie');
});

test('two edits based on the same version produce a conflict for the second', function () {
    $s = fieldScenario();
    $household = (string) Str::uuid();
    $house = (string) Str::uuid();
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('house', 'create', $house, ['block_id' => $s['block']->id]),
        op('household', 'create', $household, ['house_uuid' => $house, 'residence_type' => 'usual']),
    ]));

    $response = $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('household', 'update', $household, ['residence_type' => 'temporary', 'contact_name' => 'First'], 1),
        op('household', 'update', $household, ['residence_type' => 'usual', 'contact_name' => 'Second'], 1),
    ]));

    expect($response->json('results.0.status'))->toBe('applied')
        ->and($response->json('results.1.status'))->toBe('conflict')
        ->and($response->json('results.1.code'))->toBe('edit_edit')
        ->and(Household::query()->sole()->contact_name)->toBe('First');
});

test('a supervisor resolves a conflict by sending the device values as a correction', function () {
    $s = fieldScenario();
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, offlineHouseOperations($s)));
    $occupant = Occupant::query()->where('full_name', 'Child Kollie')->firstOrFail();
    $supervisor = staffUser(RoleName::CensusSupervisor, $s['town']);
    app(CensusWorkflow::class)->verify($supervisor, $occupant->household);
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, [
        op('occupant', 'update', $occupant->uuid, ['full_name' => 'Child Kollie', 'age_years' => 10, 'sex' => 'male', 'relationship_to_head' => 'child', 'usual_resident' => true], 1),
    ]));
    $conflict = SyncConflict::query()->sole();

    $this->actingAs($supervisor)
        ->patch(route('admin.sync.conflicts.update', $conflict), ['decision' => 'convert_correction', 'note' => 'Enumerator measured age again'])
        ->assertSessionHasNoErrors();

    expect($conflict->fresh()->status)->toBe(ConflictStatus::ConvertedToCorrection)
        ->and($occupant->corrections()->sole()->changes)->toBe(['age_years' => ['from' => 8, 'to' => 10]])
        ->and($occupant->fresh()->age_years)->toBe(8);
});

test('synchronised records do not count as official until approved', function () {
    $s = fieldScenario();
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, offlineHouseOperations($s)));

    $summary = app(CensusStatistics::class)->summary($s['campaign'], null);

    expect($summary['occupants']['submitted'])->toBe(4)
        ->and($summary['occupants']['approved'])->toBe(0);
});

test('a revoked device is refused and told to wipe its data', function () {
    $s = fieldScenario();
    app(RegisterDevice::class)->revoke($s['device'], null, 'Reported stolen');

    $this->withToken($s['token'])->getJson(route('api.v1.field.session'))->assertUnauthorized();
    $this->getJson(route('api.v1.devices.status', $s['device']->uuid))->assertJson(['revoked' => true]);
});

test('personal web-session tokens cannot use the field API', function () {
    $s = fieldScenario();
    $plain = $s['enumerator']->createToken('script', ['read'])->plainTextToken;

    $this->withToken($plain)->getJson(route('api.v1.field.session'))
        ->assertUnauthorized()
        ->assertJson(['reason' => 'device_token_required']);
});

test('the assignment package contains only the enumerator\'s own households and no house coordinates', function () {
    $s = fieldScenario();
    $this->withToken($s['token'])->postJson(route('api.v1.sync.batches.store'), batch($s, offlineHouseOperations($s)));
    House::query()->update(['latitude' => 6.3, 'longitude' => -10.8]);

    $other = staffUser(RoleName::CensusEnumerator);
    CampaignAssignment::factory()->for($s['campaign'], 'campaign')->for($other)->inArea($s['community'])->create();
    ['token' => $otherToken] = app(RegisterDevice::class)->handle($other, 'Tablet 9', 'test');

    $mine = $this->withToken($s['token'])->getJson(route('api.v1.field.package', $s['campaign']))->assertOk();
    app('auth')->forgetGuards();
    $theirs = $this->withToken($otherToken)->getJson(route('api.v1.field.package', $s['campaign']))->assertOk();

    expect($mine->json('households'))->toHaveCount(2)
        ->and($mine->json('occupants'))->toHaveCount(4)
        ->and($mine->json('houses.0'))->not->toHaveKey('latitude')
        ->and($theirs->json('households'))->toHaveCount(0)
        ->and($theirs->json('houses'))->toHaveCount(1);
});

test('enumerators without an assignment in the campaign get no package', function () {
    $s = fieldScenario();
    $outsider = User::factory()->create();
    $outsider->assignRole(RoleName::CensusEnumerator->value);
    ['token' => $token] = app(RegisterDevice::class)->handle($outsider, 'Tablet', 'test');

    $this->withToken($token)->getJson(route('api.v1.field.package', $s['campaign']))->assertNotFound();
});
