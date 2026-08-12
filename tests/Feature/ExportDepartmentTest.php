<?php

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Kept inactive so it stays out of the default (status=active) export
    // scope and doesn't pollute row-count assertions below.
    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
        'status' => 'inactive',
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
 * @return list<list<string>>
 */
function parseExportedCsv(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = array_filter(explode("\n", trim($content)), fn (string $line): bool => $line !== '');

    return array_map('str_getcsv', $lines);
}

test('exportDepartments_defaultStatus_returnsOnlyActiveDepartments', function () {
    // Arrange
    Department::factory()->create([
        'name' => 'Engineering',
        'slug' => 'engineering',
        'description' => 'Builds things',
        'status' => 'active',
    ]);
    Department::factory()->create(['name' => 'Old Dept', 'slug' => 'old-dept', 'status' => 'inactive']);

    // Act
    $response = $this->get('/api/exports?type=department');

    // Assert
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('X-Accel-Buffering'))->toBe('no');

    $rows = parseExportedCsv($response->streamedContent());

    expect($rows[0])->toBe(['name', 'slug', 'description', 'status'])
        ->and($rows)->toHaveCount(2)
        ->and($rows[1])->toBe(['Engineering', 'engineering', 'Builds things', 'active']);
});

test('exportDepartments_statusInactive_returnsOnlyInactiveDepartments', function () {
    // Arrange
    Department::factory()->create(['name' => 'Engineering', 'slug' => 'engineering', 'status' => 'active']);
    Department::factory()->create(['name' => 'Old Dept', 'slug' => 'old-dept', 'status' => 'inactive']);

    // Act
    $response = $this->get('/api/exports?type=department&status=inactive');

    // Assert
    $rows = parseExportedCsv($response->streamedContent());
    $slugs = collect($rows)->skip(1)->pluck(1);

    expect($slugs)->toContain('admin-department', 'old-dept')
        ->and($slugs)->not->toContain('engineering');
});

test('exportDepartments_statusAll_returnsEveryStatus', function () {
    // Arrange
    Department::factory()->create(['name' => 'Engineering', 'slug' => 'engineering', 'status' => 'active']);
    Department::factory()->create(['name' => 'Old Dept', 'slug' => 'old-dept', 'status' => 'inactive']);

    // Act
    $response = $this->get('/api/exports?type=department&status=all');

    // Assert
    $rows = parseExportedCsv($response->streamedContent());
    $slugs = collect($rows)->skip(1)->pluck(1);

    expect($slugs)->toContain('admin-department', 'engineering', 'old-dept');
});

test('exportDepartments_excludesSoftDeletedDepartments', function () {
    // Arrange
    $trashed = Department::factory()->create(['name' => 'Trashed Dept', 'slug' => 'trashed-dept', 'status' => 'active']);
    $trashed->delete();

    // Act
    $response = $this->get('/api/exports?type=department&status=all');

    // Assert
    $rows = parseExportedCsv($response->streamedContent());

    expect(collect($rows)->skip(1)->pluck(1))->not->toContain('trashed-dept');
});

test('exportDepartments_noActiveDepartments_returnsHeaderOnlyCsv', function () {
    // Act
    $response = $this->get('/api/exports?type=department');

    // Assert
    $rows = parseExportedCsv($response->streamedContent());

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBe(['name', 'slug', 'description', 'status']);
});

test('exportDepartments_nonAdminUser_forbidden', function () {
    // Arrange
    $department = Department::factory()->create(['status' => 'active']);
    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'employee',
            'department_id' => $department->id,
        ]),
        ['profile:read', 'employees:read', 'departments:read']
    );

    // Act
    $response = $this->get('/api/exports?type=department');

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('success', false);
});
