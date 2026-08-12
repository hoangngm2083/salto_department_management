<?php

namespace App\Enums;

enum JobApplicationStatus: string
{
    case Submitted = 'submitted';
    case Screening = 'screening';
    case InterviewInvited = 'interview_invited';
    case Interviewed = 'interviewed';
    case Offered = 'offered';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case OfferDeclined = 'offer_declined';
    case Withdrawn = 'withdrawn';
}
