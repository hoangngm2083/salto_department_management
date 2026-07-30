<?php

use App\Models\Department;
use App\Models\Employee;
use App\Services\Import\Handlers\EmployeeImportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * EmployeeImportHandler's interesting logic (rowRules, attributesFor,
 * businessKey, findExistingByKeys, bulkUpsert) lives in protected methods
 * inherited from AbstractImportHandler's Template Method contract. Reflection
 * lets us exercise each piece in isolation instead of only through the full
 * HTTP -> queue -> DB pipeline already covered by ImportEmployeeTest.
 */
function callEmployeeHandlerMethod(EmployeeImportHandler $handler, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($handler, $method);

    return $reflection->invoke($handler, ...$args);
}

// --- headers() / businessKey() ------------------------------------------------

test('headers_returnsExpectedColumnsInOrder', function () {
    $handler = new EmployeeImportHandler;

    expect($handler->headers())->toBe(['name', 'email', 'birthday', 'position', 'department_slug']);
});

test('businessKey_returnsEmail', function () {
    $handler = new EmployeeImportHandler;

    expect(callEmployeeHandlerMethod($handler, 'businessKey'))->toBe('email');
});

// --- rowRules() ----------------------------------------------------------------

test('rowRules_validRow_passesValidation', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'position' => 'employee', 'department_slug' => 'engineering'];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeFalse();
});

test('rowRules_missingRequiredFields_fails', function () {
    $handler = new EmployeeImportHandler;
    $row = ['name' => null, 'email' => null, 'birthday' => null, 'position' => null, 'department_slug' => null];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue()
        ->and($validator->errors()->has('birthday'))->toBeTrue()
        ->and($validator->errors()->has('department_slug'))->toBeTrue()
        ->and($validator->errors()->has('position'))->toBeFalse();
});

test('rowRules_invalidEmailFormat_fails', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'not-an-email', 'birthday' => '1990-05-12', 'position' => 'employee', 'department_slug' => 'engineering'];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
});

test('rowRules_emailDiffersOnlyByCase_isValidatedByFormatOnlyNotNormalized', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'Jane@Example.com', 'birthday' => '1990-05-12', 'position' => 'employee', 'department_slug' => 'engineering'];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeFalse()
        ->and($validator->validated()['email'])->toBe('Jane@Example.com');
});

test('rowRules_invalidPosition_fails', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'position' => 'ceo', 'department_slug' => 'engineering'];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('position'))->toBeTrue();
});

test('rowRules_nullPosition_passesValidation', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'position' => null, 'department_slug' => 'engineering'];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeFalse();
});

test('rowRules_departmentSlugDoesNotExist_fails', function () {
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'position' => 'employee', 'department_slug' => 'missing-department'];

    $validator = Validator::make($row, callEmployeeHandlerMethod($handler, 'rowRules', [$row]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('department_slug'))->toBeTrue();
});

// --- attributesFor() -------------------------------------------------------------

test('attributesFor_newEmployee_generatesRandomHashedPassword', function () {
    $department = Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'position' => 'manager', 'department_slug' => 'engineering'];

    $attributes = callEmployeeHandlerMethod($handler, 'attributesFor', [$row]);

    expect($attributes['name'])->toBe('Jane Doe')
        ->and($attributes['department_id'])->toBe($department->id)
        ->and($attributes['birthday'])->toBe('1990-05-12')
        ->and($attributes['position'])->toBe('manager')
        ->and($attributes['deleted_at'])->toBeNull()
        ->and($attributes['password'])->toBeString()
        ->and($attributes['password'])->not->toBe('');
});

test('attributesFor_missingPosition_defaultsToEmployee', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;
    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'department_slug' => 'engineering'];

    $attributes = callEmployeeHandlerMethod($handler, 'attributesFor', [$row]);

    expect($attributes['position'])->toBe('employee');
});

test('attributesFor_existingEmployee_reusesExistingPasswordHashInsteadOfResetting', function () {
    Department::factory()->create(['slug' => 'engineering']);
    $existing = Employee::factory()->create(['email' => 'jane@example.com']);
    $handler = new EmployeeImportHandler;

    callEmployeeHandlerMethod($handler, 'findExistingByKeys', [['jane@example.com']]);

    $row = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'birthday' => '1990-05-12', 'position' => 'employee', 'department_slug' => 'engineering'];
    $attributes = callEmployeeHandlerMethod($handler, 'attributesFor', [$row]);

    expect($attributes['password'])->toBe($existing->password);
});

// --- findExistingByKeys() --------------------------------------------------------

test('findExistingByKeys_matchesActiveAndSoftDeletedIncludesMissing', function () {
    $handler = new EmployeeImportHandler;
    Employee::factory()->create(['email' => 'jane@example.com']);
    $trashed = Employee::factory()->create(['email' => 'john@example.com']);
    $trashed->delete();

    $found = callEmployeeHandlerMethod($handler, 'findExistingByKeys', [['jane@example.com', 'john@example.com', 'missing@example.com']]);

    expect($found->keys()->all())->toEqualCanonicalizing(['jane@example.com', 'john@example.com'])
        ->and($found->get('jane@example.com'))->toBeInstanceOf(Employee::class)
        ->and($found->get('john@example.com')->trashed())->toBeTrue()
        ->and($found->has('missing@example.com'))->toBeFalse();
});

// --- bulkUpsert() -----------------------------------------------------------------

test('bulkUpsert_newEmail_createsEmployee', function () {
    $department = Department::factory()->create(['slug' => 'engineering']);
    $handler = new EmployeeImportHandler;

    callEmployeeHandlerMethod($handler, 'bulkUpsert', [[
        ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'department_id' => $department->id, 'birthday' => '1990-05-12', 'position' => 'employee', 'password' => Hash::make('secret123'), 'deleted_at' => null],
    ]]);

    $this->assertDatabaseHas('employees', [
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
        'department_id' => $department->id,
        'position' => 'employee',
    ]);
});

test('bulkUpsert_existingEmail_updatesAttributes', function () {
    $department = Department::factory()->create(['slug' => 'engineering']);
    $otherDepartment = Department::factory()->create(['slug' => 'sales']);
    Employee::factory()->create([
        'email' => 'jane@example.com',
        'name' => 'Old Name',
        'department_id' => $otherDepartment->id,
        'position' => 'employee',
    ]);
    $handler = new EmployeeImportHandler;

    callEmployeeHandlerMethod($handler, 'bulkUpsert', [[
        ['email' => 'jane@example.com', 'name' => 'New Name', 'department_id' => $department->id, 'birthday' => '1991-01-01', 'position' => 'manager', 'password' => Hash::make('secret123'), 'deleted_at' => null],
    ]]);

    $this->assertDatabaseHas('employees', [
        'email' => 'jane@example.com',
        'name' => 'New Name',
        'department_id' => $department->id,
        'position' => 'manager',
    ]);
});

test('bulkUpsert_softDeletedEmail_unTrashesEmployee', function () {
    $department = Department::factory()->create(['slug' => 'engineering']);
    $employee = Employee::factory()->create(['email' => 'jane@example.com', 'department_id' => $department->id]);
    $employee->delete();
    $handler = new EmployeeImportHandler;

    callEmployeeHandlerMethod($handler, 'bulkUpsert', [[
        ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'department_id' => $department->id, 'birthday' => '1990-05-12', 'position' => 'employee', 'password' => Hash::make('secret123'), 'deleted_at' => null],
    ]]);

    expect(Employee::withTrashed()->where('email', 'jane@example.com')->first()->trashed())->toBeFalse();
});
