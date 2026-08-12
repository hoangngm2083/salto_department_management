<?php

namespace App\Services\Recruitment;

use App\Enums\JobPostingChannelStatus;

final readonly class ChannelDispatchResult
{
    private function __construct(
        public JobPostingChannelStatus $status,
        public ?string $externalRef = null,
        public ?string $errorMessage = null,
    ) {}

    public static function dispatched(?string $externalRef = null): self
    {
        return new self(JobPostingChannelStatus::Dispatched, externalRef: $externalRef);
    }

    public static function failed(string $errorMessage): self
    {
        return new self(JobPostingChannelStatus::Failed, errorMessage: $errorMessage);
    }
}
