<?php

use App\Jobs\Import\ImportChunkJob;
use Tests\TestCase;

uses(TestCase::class);

test('tries_isBoundedAndSourcedFromConfig', function () {
    config(['imports.max_retry' => 4]);

    $job = new ImportChunkJob(1, '/tmp/import.csv', 0, 1000, 2);

    expect($job->tries)->toBe(4);
});

test('backoff_isNonEmptyAndStrictlyBounded', function () {
    $job = new ImportChunkJob(1, '/tmp/import.csv', 0, 1000, 2);

    expect($job->backoff)->not->toBeEmpty()
        ->and($job->backoff)->each->toBeInt()
        ->and(max($job->backoff))->toBeLessThan(300);
});
