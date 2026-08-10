<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Level;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
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

test('getLevels_defaultStatus_activeLevelsReturned', function () {
    // Arrange
    $initialActiveCount = Level::where('status', 'active')->count();

    Level::factory()->create(['name' => 'Junior', 'slug' => 'junior', 'rank' => 100, 'status' => 'active']);
    Level::factory()->create(['name' => 'Senior', 'slug' => 'senior', 'rank' => 101, 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/levels');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Levels retrieved successfully.');

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['active'])
        ->and($response->json('data.data'))->toHaveCount($initialActiveCount + 1);
});

test('getLevels_activeStatus_activeLevelsReturned', function () {
    // Arrange
    $initialActiveCount = Level::where('status', 'active')->count();

    Level::factory()->create(['name' => 'Active Level', 'slug' => 'active-level', 'rank' => 102, 'status' => 'active']);
    Level::factory()->create(['name' => 'Inactive Level', 'slug' => 'inactive-level', 'rank' => 103, 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/levels?status=active');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['active'])
        ->and($response->json('data.data'))->toHaveCount($initialActiveCount + 1);
});

test('getLevels_inactiveStatus_inactiveLevelsReturned', function () {
    // Arrange
    $initialInactiveCount = Level::where('status', 'inactive')->count();

    Level::factory()->create(['name' => 'Active B', 'slug' => 'active-b', 'rank' => 104, 'status' => 'active']);
    Level::factory()->create(['name' => 'Inactive B', 'slug' => 'inactive-b', 'rank' => 105, 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/levels?status=inactive');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['inactive'])
        ->and($response->json('data.data'))->toHaveCount($initialInactiveCount + 1);
});

test('getLevels_allStatus_allLevelsReturned', function () {
    // Arrange
    $initialCount = Level::count();

    Level::factory()->create(['name' => 'Active C', 'slug' => 'active-c', 'rank' => 106, 'status' => 'active']);
    Level::factory()->create(['name' => 'Inactive C', 'slug' => 'inactive-c', 'rank' => 107, 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/levels?status=all');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->sort()->values()->all();

    expect($statuses)->toBe(['active', 'inactive'])
        ->and($response->json('data.data'))->toHaveCount($initialCount + 2);
});

test('getLevels_invalidStatus_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/levels?status=unknown');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('createLevel_insertAtEnd_rankedAfterHighestExisting', function () {
    // Arrange
    Level::factory()->create(['name' => 'Junior', 'slug' => 'junior-a', 'rank' => 40]);

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Staff Engineer',
        'slug' => 'staff-engineer',
        'insert_position' => 'end',
        'probation_salary_percentage' => 85,
        'status' => 'active',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Level created successfully.')
        ->assertJsonPath('data.name', 'Staff Engineer')
        ->assertJsonPath('data.slug', 'staff-engineer')
        ->assertJsonPath('data.rank', 50)
        ->assertJsonPath('data.probation_salary_percentage', 85)
        ->assertJsonPath('data.status', 'active');
});

test('createLevel_insertAtStart_rankedBeforeLowestExisting', function () {
    // Arrange
    Level::factory()->create(['name' => 'Junior', 'slug' => 'junior-b', 'rank' => 40]);

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Newbie',
        'insert_position' => 'start',
    ]);

    // Assert
    $response->assertCreated()->assertJsonPath('data.rank', 20);
});

test('createLevel_insertAtEnd_noExistingLevels_startsAtGap', function () {
    // Arrange: RefreshDatabase gives an empty `levels` table per test - no
    // seeder populates it, so this genuinely is "the very first level".

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'First Ever',
        'insert_position' => 'end',
    ]);

    // Assert
    $response->assertCreated()->assertJsonPath('data.rank', 10);
});

test('createLevel_insertBeforeReference_rankedBetweenNeighbours', function () {
    // Arrange
    $lower = Level::factory()->create(['name' => 'Junior', 'slug' => 'junior-c', 'rank' => 40]);
    $reference = Level::factory()->create(['name' => 'Middle', 'slug' => 'middle-c', 'rank' => 50]);

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Junior II',
        'insert_position' => 'before',
        'reference_level_id' => $reference->id,
    ]);

    // Assert
    $response->assertCreated();
    $rank = $response->json('data.rank');
    expect($rank)->toBeGreaterThan($lower->rank)->toBeLessThan($reference->rank);
});

test('createLevel_insertAfterReference_rankedBetweenNeighbours', function () {
    // Arrange
    $reference = Level::factory()->create(['name' => 'Middle', 'slug' => 'middle-d', 'rank' => 50]);
    $higher = Level::factory()->create(['name' => 'Senior', 'slug' => 'senior-d', 'rank' => 60]);

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Middle II',
        'insert_position' => 'after',
        'reference_level_id' => $reference->id,
    ]);

    // Assert
    $response->assertCreated();
    $rank = $response->json('data.rank');
    expect($rank)->toBeGreaterThan($reference->rank)->toBeLessThan($higher->rank);
});

test('createLevel_noRoomBetweenAdjacentRanks_rebalancesThenInserts', function () {
    // Arrange: two levels with no integer gap between their ranks.
    $reference = Level::factory()->create(['name' => 'Junior', 'slug' => 'junior-e', 'rank' => 10]);
    $tight = Level::factory()->create(['name' => 'Middle', 'slug' => 'middle-e', 'rank' => 11]);

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Junior II',
        'insert_position' => 'after',
        'reference_level_id' => $reference->id,
    ]);

    // Assert: rebalance keeps relative order (reference still ranked below
    // the level that used to be its tight neighbour) and leaves the new
    // level correctly slotted between them.
    $response->assertCreated();
    $newRank = $response->json('data.rank');
    $referenceRank = $reference->fresh()->rank;
    $tightRank = $tight->fresh()->rank;

    expect($referenceRank)->toBeLessThan($newRank)
        ->and($newRank)->toBeLessThan($tightRank);
});

test('createLevel_missingSlug_slugGenerated', function () {
    // Arrange
    $payload = [
        'name' => 'Tech Lead',
        'insert_position' => 'end',
        'status' => 'active',
    ];

    // Act
    $response = $this->postJson('/api/levels', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.name', 'Tech Lead')
        ->assertJsonPath('data.slug', 'tech-lead');
});

test('createLevel_customSlugProvided_slugGeneratedFromName', function () {
    // Arrange
    $payload = [
        'name' => 'Tech Lead',
        'slug' => 'totally-different-slug',
        'insert_position' => 'end',
        'status' => 'active',
    ];

    // Act
    $response = $this->postJson('/api/levels', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.name', 'Tech Lead')
        ->assertJsonPath('data.slug', 'tech-lead');
});

test('createLevel_missingName_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/levels', [
        'insert_position' => 'end',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['name'], 'errors');
});

test('createLevel_duplicateSlug_validationError', function () {
    // Arrange
    Level::factory()->create(['name' => 'Existing', 'slug' => 'existing', 'rank' => 69]);

    // Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Existing',
        'slug' => 'existing',
        'insert_position' => 'end',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug'], 'errors');
});

test('createLevel_missingInsertPosition_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/levels', [
        'name' => 'No Position',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['insert_position'], 'errors');
});

test('createLevel_beforePositionMissingReference_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Dangling',
        'insert_position' => 'before',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reference_level_id'], 'errors');
});

test('createLevel_probationPercentageOutOfRange_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/levels', [
        'name' => 'Overflowing',
        'insert_position' => 'end',
        'probation_salary_percentage' => 150,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['probation_salary_percentage'], 'errors');
});

test('getLevel_existingSlug_levelReturned', function () {
    // Arrange
    $level = Level::factory()->create([
        'name' => 'Principal',
        'slug' => 'principal',
        'rank' => 80,
    ]);

    // Act
    $response = $this->getJson("/api/levels/{$level->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Level retrieved successfully.')
        ->assertJsonPath('data.id', $level->id)
        ->assertJsonPath('data.name', 'Principal')
        ->assertJsonPath('data.slug', 'principal');
});

test('getLevel_unknownSlug_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/levels/does-not-exist');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('updateLevel_validPayload_updated', function () {
    // Arrange
    $level = Level::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'rank' => 81,
        'status' => 'active',
    ]);

    $payload = [
        'name' => 'New Name',
        'slug' => 'new-name',
        'rank' => 82,
        'status' => 'inactive',
    ];

    // Act
    $response = $this->putJson("/api/levels/{$level->slug}", $payload);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Level updated successfully.')
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.slug', 'new-name')
        ->assertJsonPath('data.rank', 82)
        ->assertJsonPath('data.status', 'inactive');

    $this->assertDatabaseHas('levels', [
        'id' => $level->id,
        'name' => 'New Name',
        'slug' => 'new-name',
        'status' => 'inactive',
    ]);
});

test('updateLevel_unchangedName_slugUnchanged', function () {
    // Arrange
    $level = Level::factory()->create([
        'name' => 'Keep Slug',
        'slug' => 'keep-slug',
        'rank' => 83,
    ]);

    // Act
    $response = $this->putJson("/api/levels/{$level->slug}", [
        'name' => 'Keep Slug',
        'rank' => 83,
        'status' => 'active',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.slug', 'keep-slug')
        ->assertJsonPath('data.name', 'Keep Slug');
});

test('deleteLevel_existingSlug_softDeleted', function () {
    // Arrange
    $level = Level::factory()->create(['name' => 'To Delete', 'slug' => 'to-delete', 'rank' => 84]);

    // Act
    $response = $this->deleteJson("/api/levels/{$level->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Level deleted successfully.')
        ->assertJsonPath('data', null);

    $this->assertSoftDeleted($level);
});

test('getLevel_deletedSlug_notFound', function () {
    // Arrange
    $level = Level::factory()->create(['name' => 'Already Deleted', 'slug' => 'already-deleted', 'rank' => 85]);
    $level->delete();

    // Act
    $response = $this->getJson("/api/levels/{$level->slug}");

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});
