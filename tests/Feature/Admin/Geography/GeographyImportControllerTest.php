<?php

use App\Enums\Access\RoleName;
use App\Enums\Imports\ImportStatus;
use App\Models\Geography\Community;
use App\Models\Geography\County;
use App\Models\Geography\District;
use App\Models\Imports\ImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function geographyCsv(array $rows): UploadedFile
{
    $lines = [implode(',', ['level', 'reference_code', 'name', 'parent_reference_code', 'district_number', 'description', 'latitude', 'longitude', 'verification_status'])];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    return UploadedFile::fake()->createWithContent('geography.csv', implode("\n", $lines)."\n");
}

beforeEach(function () {
    Storage::fake('local');
});

test('a valid file is previewed and then committed as a full hierarchy', function () {
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $file = geographyCsv([
        ['county', 'IMP', 'Import County', '', '', '', '', '', 'verified'],
        ['district', 'IMP-D01', 'District #1', 'IMP', '1', '', '', '', 'unverified'],
        ['clan', 'IMP-D01-CL001', 'Import Clan', 'IMP-D01', '', '', '', '', ''],
        ['town', 'IMP-T1', 'Import Town', 'IMP-D01-CL001', '', '', '6.30', '-10.80', ''],
        ['community', '', 'Import Community', 'IMP-T1', '', 'Riverside', '', '', ''],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.geography.imports.store'), ['file' => $file])
        ->assertRedirect();

    $import = ImportJob::query()->sole();

    expect($import->status)->toBe(ImportStatus::Validated)
        ->and($import->total_rows)->toBe(5)
        ->and(County::query()->where('reference_code', 'IMP')->exists())->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.geography.imports.update', $import), ['action' => 'commit'])
        ->assertRedirect();

    $community = Community::query()->where('name', 'Import Community')->sole();

    expect($import->fresh()->status)->toBe(ImportStatus::Committed)
        ->and($import->fresh()->imported_rows)->toBe(5)
        ->and($community->reference_code)->toBe('IMP-T1-C001')
        ->and($community->county_id)->toBe(County::query()->where('reference_code', 'IMP')->value('id'))
        ->and(District::query()->where('reference_code', 'IMP-D01')->value('district_number'))->toBe(1);
});

test('invalid rows are reported and the file cannot be committed', function () {
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $file = geographyCsv([
        ['district', '', 'Orphan District', 'NOPE', '', '', '', '', ''],
        ['village', '', 'Wrong Level', '', '', '', '', '', ''],
        ['county', 'BAD', 'Bad Coordinates', '', '', '', '95', '0', ''],
    ]);

    $this->actingAs($admin)->post(route('admin.geography.imports.store'), ['file' => $file]);

    $import = ImportJob::query()->sole();
    $messages = $import->rowErrors()->pluck('message', 'row_number');

    expect($import->status)->toBe(ImportStatus::Invalid)
        ->and($import->invalid_rows)->toBe(3)
        ->and($messages[2])->toContain('No county with reference NOPE')
        ->and($messages[3])->toContain('Level must be one of')
        ->and($messages[4])->toBe('Coordinates are out of range.');

    $this->actingAs($admin)
        ->patch(route('admin.geography.imports.update', $import), ['action' => 'commit'])
        ->assertSessionHasErrors('import');

    expect(County::query()->where('reference_code', 'BAD')->exists())->toBeFalse();
});

test('parents outside the uploader area are rejected', function () {
    $mine = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);
    $file = geographyCsv([
        ['community', '', 'Smuggled Community', $other['town']->reference_code, '', '', '', '', ''],
    ]);

    $this->actingAs($admin)->post(route('admin.geography.imports.store'), ['file' => $file]);

    expect(ImportJob::query()->sole()->rowErrors()->sole()->message)->toBe('This parent is outside your assigned areas.');
});

test('imports cannot be viewed by another uploader', function () {
    $chain = geoChain();
    $owner = staffUser(RoleName::CountyAdministrator, $chain['county']);
    $other = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($owner)->post(route('admin.geography.imports.store'), [
        'file' => geographyCsv([['community', '', 'Mine', $chain['town']->reference_code, '', '', '', '', '']]),
    ]);

    $this->actingAs($other)
        ->get(route('admin.geography.imports.show', ImportJob::query()->sole()))
        ->assertNotFound();
});

test('executables disguised as spreadsheets are rejected', function () {
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    // A real temporary file, so the MIME type is sniffed from content rather than the name.
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\x00", 64));

    $this->actingAs($admin)
        ->post(route('admin.geography.imports.store'), [
            'file' => new UploadedFile($path, 'areas.csv', null, null, true),
        ])
        ->assertSessionHasErrors('file');

    expect(ImportJob::query()->count())->toBe(0);
});
