<?php

use App\Enums\Access\RoleName;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\Sex;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Geography\Block;
use App\Models\Reports\ExportJob;
use App\Services\Reports\ReportExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Create a household on the given block with occupants of the given sexes.
 *
 * @param  list<Sex>  $sexes
 */
function householdWithPeople(Block $block, CensusCampaign $campaign, CensusRecordStatus $status, array $sexes, array $occupant = []): Household
{
    $household = Household::factory()->inStatus($status)->create([
        'house_id' => House::factory()->onBlock($block)->create()->id,
        'campaign_id' => $campaign->id,
    ]);

    foreach ($sexes as $sex) {
        Occupant::factory()->inHousehold($household)->create(['sex' => $sex, 'age_years' => 30, 'status' => $status, ...$occupant]);
    }

    return $household;
}

/**
 * @return array<string, mixed>
 */
function previewRows($response): array
{
    return $response->viewData('page')['props']['rows'];
}

test('the report hub only lists reports the user may open', function () {
    $chain = geoChain();
    $complaintOfficer = staffUser(RoleName::ComplaintOfficer, $chain['county']);
    $enumerator = staffUser(RoleName::CensusEnumerator, $chain['county']);

    $this->actingAs($complaintOfficer)->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/reports/index')
            ->where('reports', fn ($reports) => collect($reports)->pluck('key')->all() === ['complaints']));

    $this->actingAs($enumerator)->get(route('admin.reports.index'))->assertForbidden();
    $this->actingAs($complaintOfficer)->get(route('admin.reports.show', 'occupant-list'))->assertNotFound();
    $this->actingAs($complaintOfficer)->get(route('admin.reports.show', 'no-such-report'))->assertNotFound();
});

test('report figures count approved records inside the viewer reach only', function () {
    $mine = geoChain();
    $other = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($mine['county'])->covering($other['county'])->create();
    householdWithPeople($mine['block'], $campaign, CensusRecordStatus::Approved, [Sex::Male, Sex::Female, Sex::Female]);
    householdWithPeople($mine['block'], $campaign, CensusRecordStatus::Submitted, [Sex::Male]);
    householdWithPeople($other['block'], $campaign, CensusRecordStatus::Approved, [Sex::Male, Sex::Male]);
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $rows = previewRows($this->actingAs($admin)->get(route('admin.reports.show', ['report' => 'age-sex', 'campaign' => $campaign->id]))->assertOk());
    $allAges = collect($rows)->firstWhere('age_group', 'All ages');

    expect($allAges)->toMatchArray(['male' => 1, 'female' => 2, 'total' => 3])
        ->and(collect($rows)->firstWhere('age_group', '25-44')['total'])->toBe(3);

    $population = previewRows($this->actingAs($admin)->get(route('admin.reports.show', [
        'report' => 'population', 'campaign' => $campaign->id, 'level' => 'county', 'area_id' => $mine['county']->id,
    ])));

    expect($population)->toHaveCount(1)
        ->and($population[0])->toMatchArray(['area' => $mine['district']->name, 'occupants_approved' => 3, 'occupants_registered' => 4, 'households_approved' => 1]);
});

test('a campaign outside the user reach cannot be requested', function () {
    $mine = geoChain();
    $other = geoChain();
    CensusCampaign::factory()->active()->covering($mine['county'])->create();
    $hidden = CensusCampaign::factory()->active()->covering($other['county'])->create();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $this->actingAs($admin)
        ->get(route('admin.reports.show', ['report' => 'population', 'campaign' => $hidden->id]))
        ->assertSessionHasErrors('campaign');

    $this->actingAs($admin)
        ->post(route('admin.reports.exports.store', 'population'), ['format' => 'csv', 'filters' => ['campaign' => $hidden->id]])
        ->assertSessionHasErrors('campaign');

    expect(ExportJob::query()->count())->toBe(0);
});

test('exports are generated privately, downloadable only by the requester, and logged', function () {
    $chain = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    householdWithPeople($chain['block'], $campaign, CensusRecordStatus::Approved, [Sex::Male, Sex::Female]);
    $viewer = staffUser(RoleName::ReportViewer, $chain['county']);
    $colleague = staffUser(RoleName::ReportViewer, $chain['county']);

    $this->actingAs($viewer)
        ->post(route('admin.reports.exports.store', 'population'), ['format' => 'csv', 'filters' => ['campaign' => $campaign->id, 'breakdown' => 'county']])
        ->assertSessionHasNoErrors();

    $export = ExportJob::query()->sole();

    expect($export->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($export->classification)->toBe('aggregate')
        ->and($export->parameters)->toMatchArray(['campaign' => $campaign->id, 'breakdown' => 'county'])
        ->and($export->row_count)->toBeGreaterThanOrEqual(1)
        ->and($export->file_path)->toStartWith('exports/');

    Storage::disk('local')->assertExists($export->file_path);
    $csv = Storage::disk('local')->get($export->file_path);
    expect($csv)->toContain('"Occupants (approved)"')->toContain($chain['county']->name);

    $this->actingAs($colleague)->get(route('admin.exports.download', $export))->assertNotFound();
    $this->actingAs($viewer)->get(route('admin.exports.download', $export))
        ->assertOk()
        ->assertHeader('content-disposition');

    expect($export->fresh()->download_count)->toBe(1)
        ->and(Activity::query()->where('log_name', 'reports')->where('event', 'downloaded')->count())->toBe(1);
});

test('excel and pdf exports are produced', function (string $format) {
    $chain = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    householdWithPeople($chain['block'], $campaign, CensusRecordStatus::Approved, [Sex::Female]);
    $viewer = staffUser(RoleName::ReportViewer, $chain['county']);

    $this->actingAs($viewer)
        ->post(route('admin.reports.exports.store', 'age-sex'), ['format' => $format, 'filters' => ['campaign' => $campaign->id]])
        ->assertSessionHasNoErrors();

    $export = ExportJob::query()->sole();

    expect($export->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($export->row_count)->toBe(8);
    expect(Storage::disk('local')->size($export->file_path))->toBeGreaterThan(500);
})->with(['xlsx', 'pdf']);

test('personal data reports are logged when viewed and need extra permission and a purpose to export', function () {
    $chain = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    householdWithPeople($chain['block'], $campaign, CensusRecordStatus::Approved, [Sex::Female], ['full_name' => 'Comfort Kollie']);
    $countyAdmin = staffUser(RoleName::CountyAdministrator, $chain['county']);
    $national = staffUser(RoleName::NationalCensusAdministrator);
    $reportViewer = staffUser(RoleName::ReportViewer, $chain['county']);

    $this->actingAs($reportViewer)->get(route('admin.reports.show', 'occupant-list'))->assertNotFound();

    $this->actingAs($countyAdmin)->get(route('admin.reports.show', ['report' => 'occupant-list', 'campaign' => $campaign->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.full_name', 'Comfort Kollie')->where('report.can_export', false));

    expect(Activity::query()->where('log_name', 'reports')->where('event', 'viewed')->where('causer_id', $countyAdmin->id)->exists())->toBeTrue();

    $this->actingAs($countyAdmin)
        ->post(route('admin.reports.exports.store', 'occupant-list'), ['format' => 'csv', 'purpose' => 'Verification visits next week'])
        ->assertForbidden();

    $this->actingAs($national)
        ->post(route('admin.reports.exports.store', 'occupant-list'), ['format' => 'csv', 'filters' => ['campaign' => $campaign->id]])
        ->assertSessionHasErrors('purpose');

    $this->actingAs($national)
        ->post(route('admin.reports.exports.store', 'occupant-list'), [
            'format' => 'csv',
            'purpose' => 'Verification visits next week',
            'filters' => ['campaign' => $campaign->id],
        ])
        ->assertSessionHasNoErrors();

    $export = ExportJob::query()->sole();

    expect($export->classification)->toBe('personal')
        ->and($export->purpose)->toBe('Verification visits next week')
        ->and(Storage::disk('local')->get($export->file_path))->toContain('Comfort Kollie');
});

test('spreadsheet formula injection is neutralised in csv cells', function (mixed $value, mixed $expected) {
    expect(ReportExporter::csvSafe($value))->toBe($expected);
})->with([
    'formula' => ['=HYPERLINK("http://evil")', '\'=HYPERLINK("http://evil")'],
    'plus' => ['+1+1', "'+1+1"],
    'at' => ['@SUM(A1)', "'@SUM(A1)"],
    'dash text' => ['-cmd', "'-cmd"],
    'plain text' => ['Kakata', 'Kakata'],
    'negative number' => [-5, -5],
    'null' => [null, null],
]);

test('expired export files are pruned while the record of the export is kept', function () {
    $chain = geoChain();
    $viewer = staffUser(RoleName::ReportViewer, $chain['county']);
    CensusCampaign::factory()->active()->covering($chain['county'])->create();

    $this->actingAs($viewer)->post(route('admin.reports.exports.store', 'population'), ['format' => 'csv']);
    $export = ExportJob::query()->sole();
    $export->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($viewer)->get(route('admin.exports.download', $export))->assertStatus(410);

    $this->artisan('model:prune', ['--model' => [ExportJob::class]])->assertSuccessful();

    Storage::disk('local')->assertMissing("exports/{$export->uuid}.csv");
    expect($export->fresh())->not->toBeNull()
        ->and($export->fresh()->file_path)->toBeNull();
});

test('a user cannot queue unlimited exports at once', function () {
    $chain = geoChain();
    $viewer = staffUser(RoleName::ReportViewer, $chain['county']);

    ExportJob::query()->insert(array_map(fn (int $i): array => [
        'uuid' => (string) Str::uuid(), 'user_id' => $viewer->id, 'report' => 'population', 'format' => 'csv',
        'parameters' => '{}', 'classification' => 'aggregate', 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
    ], range(1, 3)));

    $this->actingAs($viewer)->post(route('admin.reports.exports.store', 'population'), ['format' => 'csv'])
        ->assertSessionHasErrors('format');
});
