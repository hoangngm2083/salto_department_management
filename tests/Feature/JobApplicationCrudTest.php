<?php

use App\Enums\JobPostingStatus;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// No Sanctum::actingAs anywhere in this file - /api/careers/* is a public,
// unauthenticated surface (routes/api.php places it outside the
// auth:sanctum group), unlike almost every other Feature test in this suite.
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('recruitment.resume.disk'));
});

/**
 * @param  array<string, mixed>  $overrides
 */
function validApplyPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '0123456789',
        'linkedin_url' => 'https://linkedin.com/in/ada',
        'cover_letter' => 'I would love to join the team.',
        'resume' => UploadedFile::fake()->create('resume.pdf', 200),
    ], $overrides);
}

// --- GET /api/careers/postings ---

test('getCareerJobPostings_mixedStatuses_onlyPublishedReturned', function () {
    // Arrange
    $published = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);
    JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);
    JobPosting::factory()->create(['status' => JobPostingStatus::Closed, 'published_at' => now()->subDay(), 'closed_at' => now()]);
    JobPosting::factory()->create(['status' => JobPostingStatus::Cancelled, 'closed_at' => now()]);

    // Act
    $response = $this->getJson('/api/careers/postings');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $ids = collect($response->json('data.data'))->pluck('id')->all();
    expect($ids)->toBe([$published->id]);
});

test('getCareerJobPostings_noPublishedPostings_emptyDataReturned', function () {
    // Arrange
    JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);

    // Act
    $response = $this->getJson('/api/careers/postings');

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toBe([]);
});

test('getCareerJobPostings_response_isCursorPaginatedShape', function () {
    // Arrange
    JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->getJson('/api/careers/postings');

    // Assert
    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data',
                'meta' => ['per_page', 'next_cursor', 'prev_cursor'],
            ],
        ]);
});

// --- GET /api/careers/postings/{jobPosting:slug} ---

test('getCareerJobPosting_publishedSlug_returnedWithPublicFields', function () {
    // Arrange
    $department = Department::factory()->create();
    $jobPosting = JobPosting::factory()->create([
        'title' => 'Backend Engineer',
        'slug' => 'backend-engineer',
        'department_id' => $department->id,
        'status' => JobPostingStatus::Published,
        'published_at' => now(),
    ]);

    // Act
    $response = $this->getJson("/api/careers/postings/{$jobPosting->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $jobPosting->id)
        ->assertJsonPath('data.title', 'Backend Engineer')
        ->assertJsonPath('data.slug', 'backend-engineer')
        ->assertJsonPath('data.department.id', $department->id)
        ->assertJsonPath('data.department.name', $department->name)
        ->assertJsonPath('data.project', null)
        ->assertJsonPath('data.employment_type', $jobPosting->employment_type->value)
        ->assertJsonPath('data.slots_needed', $jobPosting->slots_needed);

    expect($response->json('data.published_at'))->not->toBeNull();
});

test('getCareerJobPosting_draftSlug_notFound', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft, 'slug' => 'draft-posting']);

    // Act
    $response = $this->getJson("/api/careers/postings/{$jobPosting->slug}");

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
});

test('getCareerJobPosting_closedSlug_notFound', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create([
        'status' => JobPostingStatus::Closed,
        'slug' => 'closed-posting',
        'published_at' => now()->subDays(5),
        'closed_at' => now(),
    ]);

    // Act
    $response = $this->getJson("/api/careers/postings/{$jobPosting->slug}");

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
});

test('getCareerJobPosting_cancelledSlug_notFound', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create([
        'status' => JobPostingStatus::Cancelled,
        'slug' => 'cancelled-posting',
        'closed_at' => now(),
    ]);

    // Act
    $response = $this->getJson("/api/careers/postings/{$jobPosting->slug}");

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
});

test('getCareerJobPosting_unknownSlug_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/careers/postings/does-not-exist');

    // Assert: must be indistinguishable from a draft/closed/cancelled slug -
    // same status code, same response shape.
    $response->assertNotFound()->assertJsonPath('success', false);
});

// --- POST /api/careers/postings/{jobPosting:slug}/apply ---

test('applyToJobPosting_validPayload_createdWithDefaultsAndFileStored', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['email' => 'new-applicant@example.com'])
    );

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Application submitted successfully.')
        ->assertJsonPath('data.job_posting.id', $jobPosting->id)
        ->assertJsonPath('data.job_posting.slug', $jobPosting->slug)
        ->assertJsonPath('data.status', 'submitted');

    expect($response->json('data.submitted_at'))->not->toBeNull();

    $this->assertDatabaseHas('job_applications', [
        'job_posting_id' => $jobPosting->id,
        'status' => 'submitted',
        'channel' => 'company_career_page',
        'talent_pool' => false,
    ]);

    $applicant = Applicant::where('email', 'new-applicant@example.com')->first();
    expect($applicant)->not->toBeNull();

    $jobApplication = JobApplication::where('applicant_id', $applicant->id)
        ->where('job_posting_id', $jobPosting->id)
        ->first();
    expect($jobApplication)->not->toBeNull();

    Storage::disk(config('recruitment.resume.disk'))->assertExists($jobApplication->resume_path);
});

test('applyToJobPosting_responseResource_doesNotLeakInternalFields', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['email' => 'private-fields@example.com'])
    );

    // Assert
    $response->assertCreated();
    $data = $response->json('data');

    expect($data)->toHaveKeys(['id', 'job_posting', 'status', 'submitted_at'])
        ->and($data)->not->toHaveKeys([
            'reviewed_by', 'reviewed_at', 'rejection_reason', 'talent_pool',
            'offer_response', 'offer_responded_at', 'applicant_id', 'applicant', 'channel',
        ]);
});

test('applyToJobPosting_sameEmailDifferentPostings_reusesApplicantAndOverwritesContactInfo', function () {
    // Arrange
    $firstPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);
    $secondPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    $this->postJson("/api/careers/postings/{$firstPosting->slug}/apply", validApplyPayload([
        'email' => 'reused@example.com',
        'full_name' => 'Original Name',
        'phone' => '1111111111',
        'linkedin_url' => 'https://linkedin.com/in/original',
    ]))->assertCreated();

    $applicantId = Applicant::where('email', 'reused@example.com')->value('id');

    // Act: same email applies to a different posting, with different contact details.
    $response = $this->postJson("/api/careers/postings/{$secondPosting->slug}/apply", validApplyPayload([
        'email' => 'reused@example.com',
        'full_name' => 'Updated Name',
        'phone' => '2222222222',
        'linkedin_url' => 'https://linkedin.com/in/updated',
    ]));

    // Assert
    $response->assertCreated();

    $this->assertDatabaseCount('applicants', 1);
    $this->assertDatabaseHas('applicants', [
        'id' => $applicantId,
        'email' => 'reused@example.com',
        'full_name' => 'Updated Name',
        'phone' => '2222222222',
        'linkedin_url' => 'https://linkedin.com/in/updated',
    ]);

    $this->assertDatabaseHas('job_applications', ['applicant_id' => $applicantId, 'job_posting_id' => $firstPosting->id]);
    $this->assertDatabaseHas('job_applications', ['applicant_id' => $applicantId, 'job_posting_id' => $secondPosting->id]);
});

test('applyToJobPosting_duplicateEmailSamePosting_validationError', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);
    $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", validApplyPayload([
        'email' => 'duplicate@example.com',
    ]))->assertCreated();

    // Act: same email, same posting, again.
    $response = $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", validApplyPayload([
        'email' => 'duplicate@example.com',
    ]));

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email'], 'errors')
        ->assertJsonPath('errors.email.0', 'You have already applied to this position.');

    $this->assertDatabaseCount('job_applications', 1);
});

test('applyToJobPosting_honeypotFilled_validationErrorAndNothingPersisted', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['website' => 'https://spambot.example.com'])
    );

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['website'], 'errors');

    $this->assertDatabaseCount('job_applications', 0);
    $this->assertDatabaseCount('applicants', 0);
});

test('applyToJobPosting_missingRequiredFields_validationError', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", []);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'email', 'resume'], 'errors');
});

test('applyToJobPosting_invalidEmailFormat_validationError', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['email' => 'not-an-email'])
    );

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['email'], 'errors');
});

test('applyToJobPosting_invalidLinkedinUrlFormat_validationError', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['linkedin_url' => 'not-a-url'])
    );

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['linkedin_url'], 'errors');
});

test('applyToJobPosting_disallowedResumeExtension_validationError', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['resume' => UploadedFile::fake()->create('resume.txt', 100)])
    );

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['resume'], 'errors');
});

test('applyToJobPosting_resumeExceedsMaxSize_validationError', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Published, 'published_at' => now()]);
    $maxKb = (int) config('recruitment.resume.max_file_size_mb') * 1024;

    // Act
    $response = $this->postJson(
        "/api/careers/postings/{$jobPosting->slug}/apply",
        validApplyPayload(['resume' => UploadedFile::fake()->create('resume.pdf', $maxKb + 100)])
    );

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['resume'], 'errors');
});

test('applyToJobPosting_draftPosting_notFound', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);

    // Act
    $response = $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", validApplyPayload());

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
    $this->assertDatabaseCount('job_applications', 0);
});

test('applyToJobPosting_closedPosting_notFound', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create([
        'status' => JobPostingStatus::Closed,
        'published_at' => now()->subDays(3),
        'closed_at' => now(),
    ]);

    // Act
    $response = $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", validApplyPayload());

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
    $this->assertDatabaseCount('job_applications', 0);
});

test('applyToJobPosting_cancelledPosting_notFound', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Cancelled, 'closed_at' => now()]);

    // Act
    $response = $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", validApplyPayload());

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
    $this->assertDatabaseCount('job_applications', 0);
});

test('applyToJobPosting_unknownSlug_notFound', function () {
    // Arrange / Act
    $response = $this->postJson('/api/careers/postings/does-not-exist/apply', validApplyPayload());

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
});

test('applyToJobPosting_invalidPayloadAgainstDraftPosting_stillNotFoundNotUnprocessable', function () {
    // Arrange: regression test - the published-status check must run before
    // field validation, otherwise an invalid payload against an unpublished
    // (but existing) slug would surface as 422 instead of 404, leaking that
    // the slug exists to an unauthenticated caller.
    $jobPosting = JobPosting::factory()->create(['status' => JobPostingStatus::Draft]);

    // Act: deliberately invalid payload (missing full_name/email/resume).
    $response = $this->postJson("/api/careers/postings/{$jobPosting->slug}/apply", []);

    // Assert
    $response->assertNotFound()->assertJsonPath('success', false);
    $this->assertDatabaseCount('job_applications', 0);
});
