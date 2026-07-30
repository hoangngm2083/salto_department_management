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

    Department::factory()->create([
        'name' => 'Engineering',
        'slug' => 'engineering',
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
function employeeImportCsv(array $rows, array $headers = ['name', 'email', 'birthday', 'position', 'department_slug']): string
{
    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    return implode("\n", $lines)."\n";
}

test('importEmployees_validCsvNewEmails_employeesCreated', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering'],
        ['John Smith', 'john@example.com', '1988-02-20', 'manager', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
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
    $this->assertDatabaseHas('employees', ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'position' => 'employee']);
    $this->assertDatabaseHas('employees', ['email' => 'john@example.com', 'name' => 'John Smith', 'position' => 'manager']);
});

test('importEmployees_validCsvExistingEmails_employeesUpdated', function () {
    // Arrange
    $department = Department::where('slug', 'engineering')->first();
    Employee::factory()->create([
        'name' => 'Old Name',
        'email' => 'jane@example.com',
        'department_id' => $department->id,
        'position' => 'employee',
    ]);

    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Jane Doe NG', 'jane@example.com', '1990-05-12', 'manager', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
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
    $this->assertDatabaseHas('employees', [
        'email' => 'jane@example.com',
        'name' => 'Jane Doe NG',
        'position' => 'manager',
    ]);
});

test('importEmployees_unchangedRow_notRewritten', function () {
    // Arrange
    $department = Department::where('slug', 'engineering')->first();
    $employee = Employee::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'department_id' => $department->id,
        'birthday' => '1990-05-12',
        'position' => 'employee',
    ]);
    $originalUpdatedAt = $employee->updated_at;
    $originalPasswordHash = $employee->password;

    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert: this exercises the AbstractImportHandler fix — birthday is cast
    // to Carbon on the model, so a naive cast-aware comparison would always
    // see it as "changed" and rewrite the row (and its password hash) here.
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'created_count' => 0,
        'updated_count' => 1,
        'failed_count' => 0,
    ]);
    expect($employee->fresh()->updated_at->eq($originalUpdatedAt))->toBeTrue()
        ->and($employee->fresh()->password)->toBe($originalPasswordHash);
});

test('importEmployees_duplicateEmailInFile_secondRowMarkedFailed', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Team A', 'duplicate@example.com', '1990-05-12', 'employee', 'engineering'],
        ['Team B', 'duplicate@example.com', '1991-06-13', 'employee', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
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
    $this->assertDatabaseHas('employees', ['email' => 'duplicate@example.com', 'name' => 'Team A']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
        'message' => 'Duplicate email value in file.',
    ]);
});

test('importEmployees_invalidPositionValue_rowMarkedFailedOthersProcessed', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering'],
        ['Bad Position', 'bad@example.com', '1990-05-12', 'ceo', 'engineering'],
        ['John Smith', 'john@example.com', '1990-05-12', 'manager', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
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
    $this->assertDatabaseHas('employees', ['email' => 'jane@example.com']);
    $this->assertDatabaseHas('employees', ['email' => 'john@example.com']);
    $this->assertDatabaseMissing('employees', ['email' => 'bad@example.com']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
    ]);
});

test('importEmployees_departmentSlugDoesNotExist_rowMarkedFailedOthersProcessed', function () {
    // Arrange: FR-8 — a non-existent department must fail the row, never
    // auto-create the department.
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering'],
        ['No Dept', 'nodept@example.com', '1990-05-12', 'employee', 'nonexistent-department'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
    $importId = $response->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'total' => 2,
        'created_count' => 1,
        'updated_count' => 0,
        'failed_count' => 1,
    ]);
    $this->assertDatabaseHas('employees', ['email' => 'jane@example.com']);
    $this->assertDatabaseMissing('employees', ['email' => 'nodept@example.com']);
    $this->assertDatabaseMissing('departments', ['slug' => 'nonexistent-department']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
    ]);
});

test('importEmployees_missingHeaderColumn_validationError', function () {
    // Arrange
    $csv = "name,email,birthday,position\nJane Doe,jane@example.com,1990-05-12,employee\n";
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_disallowedExtension_validationError', function () {
    // Arrange
    $csv = employeeImportCsv([['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering']]);
    $file = UploadedFile::fake()->createWithContent('employees.txt', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_fileExceedsMaxSize_validationError', function () {
    // Arrange
    config(['imports.max_file_size_mb' => 1]);
    $file = UploadedFile::fake()->create('employees.csv', 2000);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_emptyFile_validationError', function () {
    // Arrange
    $file = UploadedFile::fake()->createWithContent('employees.csv', '');

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_recordsExceedMax_validationError', function () {
    // Arrange
    config(['imports.max_records' => 1]);
    $csv = employeeImportCsv([
        ['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering'],
        ['John Smith', 'john@example.com', '1990-05-12', 'employee', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_nonAdminUser_forbidden', function () {
    // Arrange
    $department = Department::where('slug', 'engineering')->first();
    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'employee',
            'department_id' => $department->id,
        ]),
        ['profile:read', 'employees:read', 'departments:read']
    );
    $csv = employeeImportCsv([['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering']]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('success', false);
});

test('getImportStatus_completedEmployeeImport_statisticsReturned', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering'],
        ['John Smith', 'john@example.com', '1990-05-12', 'manager', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file])
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

test('importEmployees_manyRows_processesWithBulkQueriesNotPerRow', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $rows = [];
    for ($i = 1; $i <= 50; $i++) {
        $rows[] = ["Employee {$i}", "employee-{$i}@example.com", '1990-05-12', 'employee', 'engineering'];
    }
    $csv = employeeImportCsv($rows);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file])
        ->json('data.import_id');

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    // Act
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Assert: department_slug validation adds exactly one extra bulk
    // `departments` query per chunk on top of Department import's baseline —
    // still bounded, never one query per row.
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'status' => 'completed',
        'created_count' => 50,
    ]);
    expect($queryCount)->toBeLessThan(60);
});

test('importEmployees_nonUtf8File_validationError', function () {
    // Arrange
    $csv = "name,email,birthday,position,department_slug\nJane\xFF Doe,jane@example.com,1990-05-12,employee,engineering\n";
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_headerOnlyNoDataRows_validationError', function () {
    // Arrange
    $csv = employeeImportCsv([]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file'], 'errors');
});

test('importEmployees_allRowsFailValidation_importMarkedFailed', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Bad One', 'bad-one@example.com', '1990-05-12', 'ceo', 'engineering'],
        ['Bad Two', 'bad-two@example.com', '1990-05-12', 'ceo', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
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

test('importEmployees_duplicateEmailAcrossChunks_secondChunkRowMarkedFailed', function () {
    // Arrange: chunk_size=1 forces the 2 rows into 2 separate ImportChunkJob
    // transactions, exercising the cross-chunk dedup path (import_seen_keys).
    config(['queue.default' => 'database', 'imports.chunk_size' => 1]);
    $csv = employeeImportCsv([
        ['Team A', 'cross-chunk-dup@example.com', '1990-05-12', 'employee', 'engineering'],
        ['Team B', 'cross-chunk-dup@example.com', '1991-06-13', 'employee', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

    // Act
    $response = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file]);
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
    $this->assertDatabaseHas('employees', ['email' => 'cross-chunk-dup@example.com', 'name' => 'Team A']);
    $this->assertDatabaseHas('import_errors', [
        'import_id' => $importId,
        'row' => 3,
        'message' => 'Duplicate email value in file.',
    ]);
});

test('getImportStatus_employeeImportWithErrors_errorDetailsIncludedInResponse', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([
        ['Team A', 'error-employee@example.com', '1990-05-12', 'employee', 'engineering'],
        ['Team B', 'error-employee@example.com', '1991-06-13', 'employee', 'engineering'],
    ]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file])
        ->json('data.import_id');
    $this->artisan('queue:work', ['--queue' => config('imports.queue'), '--stop-when-empty' => true])
        ->assertExitCode(0);

    // Act
    $response = $this->getJson("/api/imports/{$importId}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.errors.0.row', 3)
        ->assertJsonPath('data.errors.0.message', 'Duplicate email value in file.');
});

test('importEmployees_batchCancelledBeforeChunkRuns_chunkJobReturnsEarlyWithoutProcessing', function () {
    // Arrange
    config(['queue.default' => 'database']);
    $csv = employeeImportCsv([['Jane Doe', 'jane@example.com', '1990-05-12', 'employee', 'engineering']]);
    $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);
    $importId = $this->postJson('/api/imports', ['type' => 'employee', 'file' => $file])
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
    $this->assertDatabaseMissing('employees', ['email' => 'jane@example.com']);
    $this->assertDatabaseHas('imports', [
        'id' => $importId,
        'created_count' => 0,
        'updated_count' => 0,
        'failed_count' => 0,
    ]);
});
