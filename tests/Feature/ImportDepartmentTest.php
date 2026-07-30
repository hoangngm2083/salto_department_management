<?php

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('imports.disk'));

    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
        'status' => 'active',
    ]);

    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

/**
 * @param  list<list<string>>  $rows
 */
function departmentImportCsv(array $rows, array $headers = ['name', 'slug', 'description', 'status']): string
{
    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    return implode("\n", $lines)."\n";
}

test('importDepartments_validCsvNewSlugs_departmentsCreated', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Engineering', 'engineering', 'Builds products', 'active'],
        ['Marketing', 'marketing', 'Sells products', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $response->assertStatus(202)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'queued');

    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'status' => 'completed',
        'total' => 2,
        'created_count' => 2,
        'updated_count' => 0,
        'failed_count' => 0,
    ]);
    $this->assertDatabaseHas('departments', ['slug' => 'engineering', 'name' => 'Engineering']);
    $this->assertDatabaseHas('departments', ['slug' => 'marketing', 'name' => 'Marketing']);
});

test('importDepartments_missingSlug_generatesSlugFromName', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Human Resources', '', 'People operations', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $response->assertStatus(202)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'status' => 'completed',
        'total' => 1,
        'created_count' => 1,
        'updated_count' => 0,
        'failed_count' => 0,
    ]);
    $this->assertDatabaseHas('departments', [
        'slug' => 'human-resources',
        'name' => 'Human Resources',
        'description' => 'People operations',
    ]);
});

test('importDepartments_validCsvExistingSlugs_departmentsUpdated', function () {
    // Arrange
    Department::factory()->create([
        'name' => 'Old Engineering',
        'slug' => 'engineering',
        'description' => 'Old description',
        'status' => 'active',
    ]);

    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Engineering NG', 'engineering', 'New description', 'inactive'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'status' => 'completed',
        'created_count' => 0,
        'updated_count' => 1,
        'failed_count' => 0,
    ]);
    $this->assertDatabaseHas('departments', [
        'slug' => 'engineering',
        'name' => 'Engineering NG',
        'description' => 'New description',
        'status' => 'inactive',
    ]);
});

test('importDepartments_unchangedRow_notRewritten', function () {
    // Arrange
    $department = Department::factory()->create([
        'name' => 'Finance',
        'slug' => 'finance',
        'description' => 'Handles money',
        'status' => 'active',
    ]);
    $originalUpdatedAt = $department->updated_at;

    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Finance', 'finance', 'Handles money', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'created_count' => 0,
        'updated_count' => 1,
        'failed_count' => 0,
    ]);
    expect($department->fresh()->updated_at->eq($originalUpdatedAt))->toBeTrue();
});

test('importDepartments_duplicateSlugInFile_secondRowMarkedFailed', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Team A', 'duplicate-dept', 'First occurrence', 'active'],
        ['Team B', 'duplicate-dept', 'Second occurrence', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'created_count' => 1,
        'updated_count' => 0,
        'failed_count' => 1,
    ]);
    $this->assertDatabaseCount('departments', 1 + Department::where('slug', 'admin-department')->count());
    $this->assertDatabaseHas('departments', ['slug' => 'duplicate-dept', 'name' => 'Team A']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
        'message' => 'Duplicate slug value in file.',
    ]);
});

test('importDepartments_invalidStatusValue_rowMarkedFailedOthersProcessed', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Engineering', 'engineering', 'Desc', 'active'],
        ['Bad Status', 'bad-dept', 'Desc', 'unknown-status'],
        ['Operations', 'operations', 'Desc', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'total' => 3,
        'created_count' => 2,
        'updated_count' => 0,
        'failed_count' => 1,
    ]);
    $this->assertDatabaseHas('departments', ['slug' => 'engineering']);
    $this->assertDatabaseHas('departments', ['slug' => 'operations']);
    $this->assertDatabaseMissing('departments', ['slug' => 'bad-dept']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
    ]);
});

test('importDepartments_missingHeaderColumn_validationError', function () {
    // Arrange
    $csv = "name,slug,description\nEngineering,engineering,Desc\n";
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_disallowedExtension_validationError', function () {
    // Arrange
    $csv = departmentImportCsv([['Engineering', 'engineering', 'Desc', 'active']]);
    $file = UploadedFile::fake()->createWithContent('departments.txt', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_fileExceedsMaxSize_validationError', function () {
    // Arrange
    config(['imports.max_file_size_mb' => 1]);
    $file = UploadedFile::fake()->create('departments.csv', 2000);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_emptyFile_validationError', function () {
    // Arrange
    $file = UploadedFile::fake()->createWithContent('departments.csv', '');

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_recordsExceedMax_validationError', function () {
    // Arrange
    config(['imports.max_records' => 1]);
    $csv = departmentImportCsv([
        ['Engineering', 'engineering', 'Desc', 'active'],
        ['Marketing', 'marketing', 'Desc', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_nonAdminUser_forbidden', function () {
    // Arrange
    $department = Department::factory()->create(['status' => 'active']);
    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'employee',
            'department_id' => $department->id,
        ]),
        ['profile:read', 'employees:read', 'departments:read']
    );
    $csv = departmentImportCsv([['Engineering', 'engineering', 'Desc', 'active']]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('success', false);
});

test('getImportStatus_completedImport_statisticsReturned', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Engineering', 'engineering', 'Desc', 'active'],
        ['Marketing', 'marketing', 'Desc', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file])
        ->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Act
    $response = $this->getJson("/api/imports/{$importId}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress', 100)
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.processed', 2)
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.failed', 0);
});

test('importDepartments_manyRows_processesWithBulkQueriesNotPerRow', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $rows = [];
    for ($i = 1; $i <= 50; $i++) {
        $rows[] = ["Dept {$i}", "dept-{$i}", "Desc {$i}", 'active'];
    }
    $csv = departmentImportCsv($rows);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file])
        ->json('data.import_id');

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    // Act
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    // A per-row implementation issues ~3 queries per row (dedup insert +
    // existence select + insert/update) plus fixed overhead — 50 rows
    // would be ~165+ queries. Dedup claiming is now one bulk insertOrIgnore
    // plus one confirmation select for the whole chunk, so the query count
    // stays roughly constant regardless of row count.
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'status' => 'completed',
        'created_count' => 50,
    ]);
    expect($queryCount)->toBeLessThan(60);
});

test('getImportStatus_unknownId_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/imports/999999');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

test('importDepartments_missingType_validationError', function () {
    // Arrange
    $csv = departmentImportCsv([['Engineering', 'engineering', 'Desc', 'active']]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type'], 'errors');
});

test('importDepartments_nonUtf8File_validationError', function () {
    // Arrange
    $csv = "name,slug,description,status\nEngin\xFFeering,engineering,Desc,active\n";
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_headerOnlyNoDataRows_validationError', function () {
    // Arrange
    $csv = departmentImportCsv([]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importDepartments_allRowsFailValidation_importMarkedFailed', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Bad One', 'bad-one', 'Desc', 'unknown-status'],
        ['Bad Two', 'bad-two', 'Desc', 'unknown-status'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'status' => 'failed',
        'created_count' => 0,
        'updated_count' => 0,
        'failed_count' => 2,
    ]);
});

test('importDepartments_duplicateSlugAcrossChunks_secondChunkRowMarkedFailed', function () {
    // Arrange: chunk_size=1 forces the 2 rows into 2 separate ImportChunkJob
    // transactions, exercising the cross-chunk dedup path (import_seen_keys),
    // not the in-file dedup already covered by the same-chunk duplicate test.
    config(['queue.default' => 'database', 'imports.chunk_size' => 1]);
    $csv = departmentImportCsv([
        ['Team A', 'cross-chunk-dup', 'First occurrence', 'active'],
        ['Team B', 'cross-chunk-dup', 'Second occurrence', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'created_count' => 1,
        'updated_count' => 0,
        'failed_count' => 1,
    ]);
    $this->assertDatabaseHas('departments', ['slug' => 'cross-chunk-dup', 'name' => 'Team A']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
        'message' => 'Duplicate slug value in file.',
    ]);
});

test('getImportStatus_importWithErrors_errorDetailsIncludedInResponse', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([
        ['Team A', 'error-dept', 'First occurrence', 'active'],
        ['Team B', 'error-dept', 'Second occurrence', 'active'],
    ]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file])
        ->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Act
    $response = $this->getJson("/api/imports/{$importId}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.errors.0.row', 3)
        ->assertJsonPath('data.errors.0.message', 'Duplicate slug value in file.');
});

test('importDepartments_batchCancelledBeforeChunkRuns_chunkJobReturnsEarlyWithoutProcessing', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = departmentImportCsv([['Engineering', 'engineering', 'Desc', 'active']]);
    $file = UploadedFile::fake()->createWithContent('departments.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'department', 'file' => $file])
        ->json('data.import_id');

    // Act: process only the queued ProcessImportJob — it dispatches the chunk
    // batch but no chunk job has run yet.
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--once' => true])
        ->assertExitCode(0);

    $batchId = DB::table('job_batches')->latest('id')->value('id');
    Bus::findBatch($batchId)->cancel();

    // Now let the (now-cancelled) chunk job run.
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseMissing('departments', ['slug' => 'engineering']);
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'created_count' => 0,
        'updated_count' => 0,
        'failed_count' => 0,
    ]);
});
