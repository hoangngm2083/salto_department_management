<?php

use App\Models\Department;
use App\Services\Import\Handlers\DepartmentImportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * DepartmentImportHandler's interesting logic (rowRules, attributesFor,
 * businessKey, findExistingByKeys, bulkUpsert) lives in protected methods
 * inherited from AbstractImportHandler's Template Method contract. Reflection
 * lets us exercise each piece in isolation instead of only through the full
 * HTTP -> queue -> DB pipeline already covered by ImportDepartmentTest.
 */
function callHandlerMethod(DepartmentImportHandler $handler, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($handler, $method);

    return $reflection->invoke($handler, ...$args);
}

// --- headers() / businessKey() ------------------------------------------------

test('headers_returnsExpectedColumnsInOrder', function () {
    $handler = new DepartmentImportHandler;

    expect($handler->headers())->toBe(['name', 'slug', 'description', 'status']);
});

test('businessKey_returnsSlug', function () {
    $handler = new DepartmentImportHandler;

    expect(callHandlerMethod($handler, 'businessKey'))->toBe('slug');
});

// --- rowRules() ----------------------------------------------------------------

test('rowRules_validRow_passesValidation', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => 'Engineering', 'slug' => 'engineering', 'description' => 'Builds things', 'status' => 'active'];

    $validator = Validator::make($row, callHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeFalse();
});

test('rowRules_missingNameOrSlug_fails', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => null, 'slug' => null, 'description' => null, 'status' => null];

    $validator = Validator::make($row, callHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue()
        ->and($validator->errors()->has('slug'))->toBeTrue();
});

test('rowRules_nameExceedsMaxLength_fails', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => str_repeat('a', 256), 'slug' => 'engineering', 'description' => null, 'status' => null];

    $validator = Validator::make($row, callHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue();
});

test('rowRules_nullDescriptionAndStatus_passesValidation', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => 'Engineering', 'slug' => 'engineering', 'description' => null, 'status' => null];

    $validator = Validator::make($row, callHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeFalse();
});

test('normalizeRow_missingSlug_generatesFromName', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => 'Human Resources', 'slug' => null, 'description' => null, 'status' => 'active'];

    $normalized = callHandlerMethod($handler, 'normalizeRow', [$row]);
    $validator = Validator::make($normalized, callHandlerMethod($handler, 'rowRules', [$normalized]));

    expect($normalized['slug'])->toBe('human-resources')
        ->and($validator->fails())->toBeFalse();
});

test('rowRules_statusOutsideAllowedValues_fails', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => 'Engineering', 'slug' => 'engineering', 'description' => null, 'status' => 'archived'];

    $validator = Validator::make($row, callHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('status'))->toBeTrue();
});

// --- attributesFor() -------------------------------------------------------------

test('attributesFor_rowWithStatus_usesGivenStatus', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => 'Engineering', 'slug' => 'engineering', 'description' => 'Builds things', 'status' => 'inactive'];

    $attributes = callHandlerMethod($handler, 'attributesFor', [$row]);

    expect($attributes)->toBe([
        'name' => 'Engineering',
        'description' => 'Builds things',
        'status' => 'inactive',
        'deleted_at' => null,
    ]);
});

test('attributesFor_missingStatus_defaultsToActive', function () {
    $handler = new DepartmentImportHandler;
    $row = ['name' => 'Engineering', 'slug' => 'engineering'];

    $attributes = callHandlerMethod($handler, 'attributesFor', [$row]);

    expect($attributes['status'])->toBe('active')
        ->and($attributes['description'])->toBeNull()
        ->and($attributes['deleted_at'])->toBeNull();
});

// --- findExistingByKeys() --------------------------------------------------------

test('findExistingByKeys_matchesActiveAndSoftDeletedIncludesMissing', function () {
    $handler = new DepartmentImportHandler;
    Department::factory()->create(['slug' => 'engineering']);
    $trashed = Department::factory()->create(['slug' => 'sales']);
    $trashed->delete();

    $found = callHandlerMethod($handler, 'findExistingByKeys', [['engineering', 'sales', 'missing-slug']]);

    expect($found->keys()->all())->toEqualCanonicalizing(['engineering', 'sales'])
        ->and($found->get('engineering'))->toBeInstanceOf(Department::class)
        ->and($found->get('sales')->trashed())->toBeTrue()
        ->and($found->has('missing-slug'))->toBeFalse();
});

// --- bulkUpsert() -----------------------------------------------------------------

test('bulkUpsert_newSlug_createsDepartment', function () {
    $handler = new DepartmentImportHandler;

    callHandlerMethod($handler, 'bulkUpsert', [[
        ['slug' => 'engineering', 'name' => 'Engineering', 'description' => 'Builds things', 'status' => 'active', 'deleted_at' => null],
    ]]);

    $this->assertDatabaseHas('departments', [
        'slug' => 'engineering',
        'name' => 'Engineering',
        'description' => 'Builds things',
        'status' => 'active',
    ]);
});

test('bulkUpsert_existingSlug_updatesAttributes', function () {
    $handler = new DepartmentImportHandler;
    Department::factory()->create([
        'slug' => 'engineering',
        'name' => 'Old Name',
        'status' => 'inactive',
    ]);

    callHandlerMethod($handler, 'bulkUpsert', [[
        ['slug' => 'engineering', 'name' => 'New Name', 'description' => 'New desc', 'status' => 'active', 'deleted_at' => null],
    ]]);

    $this->assertDatabaseHas('departments', [
        'slug' => 'engineering',
        'name' => 'New Name',
        'description' => 'New desc',
        'status' => 'active',
    ]);
});

test('bulkUpsert_softDeletedSlug_unTrashesDepartment', function () {
    $handler = new DepartmentImportHandler;
    $department = Department::factory()->create(['slug' => 'engineering']);
    $department->delete();

    callHandlerMethod($handler, 'bulkUpsert', [[
        ['slug' => 'engineering', 'name' => 'Engineering', 'description' => null, 'status' => 'active', 'deleted_at' => null],
    ]]);

    expect(Department::withTrashed()->where('slug', 'engineering')->first()->trashed())->toBeFalse();
});
