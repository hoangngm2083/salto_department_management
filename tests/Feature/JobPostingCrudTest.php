<?php

use App\Enums\JobPostingStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
        'status' => 'active',
    ]);

    // Persisted (not ->make()) because job posting creation writes the
    // actor's id into job_postings.created_by, a NOT NULL/FK column.
    Sanctum::actingAs(
        Employee::factory()->create([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

/**
 * Creates (and persists) an HR-department manager, mirroring the fixture
 * pattern used in EmployeeCrudTest/AuthorizationTest for HR-scoped abilities.
 */
function createHrManager(): Employee
{
    $hrDepartment = Department::factory()->create(['slug' => config('departments.hr_slug')]);

    return Employee::factory()->create(['position' => 'manager', 'department_id' => $hrDepartment->id]);
}

test('createJobPosting_validPayload_createdWithDraftStatusAndDerivedSlug', function () {
    // Arrange
    $department = Department::factory()->create();

    $payload = [
        'title' => 'Senior Backend Engineer',
        'department_id' => $department->id,
        'description' => 'Build and maintain backend services.',
        'requirements' => 'PHP, Laravel, 5+ years.',
        'employment_type' => 'full_time',
        'slots_needed' => 2,
    ];

    // Act
    $response = $this->postJson('/api/job-postings', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job posting created successfully.')
        ->assertJsonPath('data.title', 'Senior Backend Engineer')
        ->assertJsonPath('data.slug', 'senior-backend-engineer')
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.employment_type', 'full_time')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.closed_at', null);

    $this->assertDatabaseHas('job_postings', [
        'title' => 'Senior Backend Engineer',
        'slug' => 'senior-backend-engineer',
        'status' => 'draft',
    ]);
});

test('createJobPosting_asHrDepartmentManager_created', function () {
    // Arrange
    $hrManager = createHrManager();
    $department = Department::factory()->create();
    Sanctum::actingAs($hrManager, ['*']);

    // Act
    $response = $this->postJson('/api/job-postings', [
        'title' => 'Recruiter',
        'department_id' => $department->id,
        'description' => 'Source and screen candidates.',
        'requirements' => '2+ years in recruitment.',
        'employment_type' => 'contract',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.title', 'Recruiter')
        ->assertJsonPath('data.slug', 'recruiter');
});

test('createJobPosting_missingRequiredFields_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/job-postings', []);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'slug', 'department_id', 'description', 'requirements', 'employment_type'], 'errors');
});

test('createJobPosting_duplicateSlug_validationError', function () {
    // Arrange
    $department = Department::factory()->create();
    JobPosting::factory()->create(['title' => 'Existing Role', 'slug' => 'existing-role']);

    // Act
    $response = $this->postJson('/api/job-postings', [
        'title' => 'Existing Role',
        'department_id' => $department->id,
        'description' => 'Desc',
        'requirements' => 'Req',
        'employment_type' => 'full_time',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug'], 'errors');
});

test('createJobPosting_invalidEmploymentType_validationError', function () {
    // Arrange
    $department = Department::factory()->create();

    // Act
    $response = $this->postJson('/api/job-postings', [
        'title' => 'Invalid Employment Type',
        'department_id' => $department->id,
        'description' => 'Desc',
        'requirements' => 'Req',
        'employment_type' => 'freelance',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_type'], 'errors');
});

test('createJobPosting_invalidStatus_validationError', function () {
    // Arrange
    $department = Department::factory()->create();

    // Act
    $response = $this->postJson('/api/job-postings', [
        'title' => 'Invalid Status',
        'department_id' => $department->id,
        'description' => 'Desc',
        'requirements' => 'Req',
        'employment_type' => 'full_time',
        'status' => 'archived',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('getJobPosting_existingSlug_returned', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['title' => 'Visible Posting', 'slug' => 'visible-posting']);

    // Act
    $response = $this->getJson("/api/job-postings/{$jobPosting->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $jobPosting->id)
        ->assertJsonPath('data.title', 'Visible Posting')
        ->assertJsonPath('data.channels', []);
});

test('getJobPosting_unknownSlug_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/job-postings/does-not-exist');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

test('updateJobPosting_partialPatchSingleField_updatedWithoutRequiringOtherFields', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['slots_needed' => 1]);

    // Act
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['slots_needed' => 5]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.slots_needed', 5)
        ->assertJsonPath('data.title', $jobPosting->title);

    $this->assertDatabaseHas('job_postings', ['id' => $jobPosting->id, 'slots_needed' => 5]);
});

test('updateJobPosting_statusOnlyPatch_succeedsWithoutTitleOrDepartment', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);

    // Act
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'cancelled']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'cancelled');
});

test('deleteJobPosting_existingSlug_softDeleted', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['title' => 'To Delete', 'slug' => 'to-delete']);

    // Act
    $response = $this->deleteJson("/api/job-postings/{$jobPosting->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job posting deleted successfully.');

    $this->assertSoftDeleted($jobPosting);
});

// --- Draft -> Published dispatch business rule ---

test('updateJobPosting_draftToPublished_publishedAtSetAndDispatchedToChannel', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft, 'published_at' => null]);

    // Act
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'published']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    expect($response->json('data.published_at'))->not->toBeNull();

    $this->assertDatabaseHas('job_posting_channels', [
        'job_posting_id' => $jobPosting->id,
        'channel' => 'company_career_page',
        'status' => 'dispatched',
    ]);

    $channel = $jobPosting->fresh()->channels()->where('channel', 'company_career_page')->first();
    expect($channel->dispatched_at)->not->toBeNull();

    $this->assertDatabaseCount('job_posting_channels', 1);
});

test('updateJobPosting_alreadyPublishedStatusResent_dispatchNotDuplicated', function () {
    // Arrange: first PATCH does the real Draft -> Published transition and dispatch.
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft, 'published_at' => null]);
    $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'published'])->assertSuccessful();

    $publishedAtAfterFirstPatch = $jobPosting->fresh()->published_at;

    // Act: second PATCH is a no-op transition (Published -> Published).
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'published']);

    // Assert: still just one channel row (updateOrCreate is idempotent, and the
    // service only dispatches on an actual Draft -> Published transition, so a
    // second "stays published" PATCH shouldn't touch published_at again either).
    $response->assertSuccessful()->assertJsonPath('data.status', 'published');

    $this->assertDatabaseCount('job_posting_channels', 1);
    expect($jobPosting->fresh()->published_at->toISOString())->toBe($publishedAtAfterFirstPatch->toISOString());
});

test('updateJobPosting_publishedAtAlreadySet_notRestamped', function () {
    // Arrange
    $originalPublishedAt = now()->subDays(3);
    $jobPosting = JobPosting::factory()->create([
        'status' => JobPostingStatus::Draft,
        'published_at' => $originalPublishedAt,
    ]);

    // Act
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'published']);

    // Assert: compare at second precision - the timestamp column truncates
    // the microseconds `now()` carries in memory.
    $response->assertSuccessful();
    expect($jobPosting->fresh()->published_at->timestamp)->toBe($originalPublishedAt->timestamp);
});

// --- Closed/Cancelled closed_at business rule ---

test('updateJobPosting_draftToClosed_closedAtStamped', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft, 'closed_at' => null]);

    // Act
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'closed']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'closed');
    expect($response->json('data.closed_at'))->not->toBeNull();

    // Closing (not publishing) must not trigger channel dispatch.
    $this->assertDatabaseCount('job_posting_channels', 0);
});

test('updateJobPosting_publishedToCancelled_closedAtStamped', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create([
        'status' => JobPostingStatus::Published,
        'published_at' => now()->subDay(),
        'closed_at' => null,
    ]);

    // Act
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'cancelled']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'cancelled');
    expect($response->json('data.closed_at'))->not->toBeNull();
});

test('updateJobPosting_closedAtAlreadySet_notRestamped', function () {
    // Arrange
    $originalClosedAt = now()->subDays(10);
    $jobPosting = JobPosting::factory()->create([
        'status' => JobPostingStatus::Closed,
        'closed_at' => $originalClosedAt,
    ]);

    // Act: re-send the same status, e.g. an idempotent client retry.
    $response = $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['status' => 'closed']);

    // Assert: compare at second precision - the timestamp column truncates
    // the microseconds `now()` carries in memory.
    $response->assertSuccessful();
    expect($jobPosting->fresh()->closed_at->timestamp)->toBe($originalClosedAt->timestamp);
});

// --- Listing filters ---

test('getJobPostings_noFilter_allStatusesReturned', function () {
    // Arrange
    JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);
    JobPosting::factory()->create(['status' => JobPostingStatus::Published]);
    JobPosting::factory()->create(['status' => JobPostingStatus::Closed]);

    // Act
    $response = $this->getJson('/api/job-postings');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Job postings retrieved successfully.');

    expect($response->json('data.data'))->toHaveCount(3);
});

test('getJobPostings_statusFilter_matchingReturned', function () {
    // Arrange
    JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);
    JobPosting::factory()->create(['status' => JobPostingStatus::Published]);

    // Act
    $response = $this->getJson('/api/job-postings?status=published');

    // Assert
    $response->assertSuccessful();
    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();
    expect($statuses)->toBe(['published']);
});

test('getJobPostings_departmentFilter_matchingReturned', function () {
    // Arrange
    $targetDepartment = Department::factory()->create();
    $matching = JobPosting::factory()->create(['department_id' => $targetDepartment->id]);
    JobPosting::factory()->create();

    // Act
    $response = $this->getJson("/api/job-postings?department_id={$targetDepartment->id}");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1)
        ->and($response->json('data.data.0.id'))->toBe($matching->id);
});

test('getJobPostings_invalidStatus_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/job-postings?status=unknown');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'], 'errors');
});

// --- Authorization matrix ---

test('jobPostingAbilities_hrDepartmentManager_allowedOnAllAbilities', function () {
    // Arrange
    $hrManager = createHrManager();
    $department = Department::factory()->create();
    $jobPosting = JobPosting::factory()->create(['department_id' => $department->id]);
    Sanctum::actingAs($hrManager, ['*']);

    // Act & Assert
    $this->getJson('/api/job-postings')->assertSuccessful();
    $this->getJson("/api/job-postings/{$jobPosting->slug}")->assertSuccessful();

    $this->postJson('/api/job-postings', [
        'title' => 'HR Created Role',
        'department_id' => $department->id,
        'description' => 'Desc',
        'requirements' => 'Req',
        'employment_type' => 'full_time',
    ])->assertCreated();

    $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['slots_needed' => 3])
        ->assertSuccessful()
        ->assertJsonPath('data.slots_needed', 3);

    $this->deleteJson("/api/job-postings/{$jobPosting->slug}")->assertSuccessful();
});

test('jobPostingAbilities_nonHrDepartmentManager_forbiddenOnAllAbilities', function () {
    // Arrange
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $jobPosting = JobPosting::factory()->create();
    Sanctum::actingAs($manager, ['*']);

    // Act & Assert
    $this->getJson('/api/job-postings')->assertForbidden()->assertJsonPath('message', 'Forbidden.');
    $this->getJson("/api/job-postings/{$jobPosting->slug}")->assertForbidden()->assertJsonPath('message', 'Forbidden.');

    $this->postJson('/api/job-postings', [
        'title' => 'Should Not Be Created',
        'department_id' => $department->id,
        'description' => 'Desc',
        'requirements' => 'Req',
        'employment_type' => 'full_time',
    ])->assertForbidden()->assertJsonPath('message', 'Forbidden.');

    $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['slots_needed' => 3])
        ->assertForbidden()->assertJsonPath('message', 'Forbidden.');

    $this->deleteJson("/api/job-postings/{$jobPosting->slug}")->assertForbidden()->assertJsonPath('message', 'Forbidden.');
});

test('jobPostingAbilities_employeePosition_forbiddenOnAllAbilities', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $jobPosting = JobPosting::factory()->create();
    Sanctum::actingAs($employee, ['*']);

    // Act & Assert
    $this->getJson('/api/job-postings')->assertForbidden()->assertJsonPath('message', 'Forbidden.');
    $this->getJson("/api/job-postings/{$jobPosting->slug}")->assertForbidden()->assertJsonPath('message', 'Forbidden.');

    $this->postJson('/api/job-postings', [
        'title' => 'Should Not Be Created',
        'department_id' => $jobPosting->department_id,
        'description' => 'Desc',
        'requirements' => 'Req',
        'employment_type' => 'full_time',
    ])->assertForbidden()->assertJsonPath('message', 'Forbidden.');

    $this->patchJson("/api/job-postings/{$jobPosting->slug}", ['slots_needed' => 3])
        ->assertForbidden()->assertJsonPath('message', 'Forbidden.');

    $this->deleteJson("/api/job-postings/{$jobPosting->slug}")->assertForbidden()->assertJsonPath('message', 'Forbidden.');
});
