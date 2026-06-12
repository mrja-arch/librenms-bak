<?php

namespace App\Enums;

enum DiscoveryCandidateStatus: string
{
    case Pending = 'pending';
    case Ignored = 'ignored';
    case Managed = 'managed';
    case Failed = 'failed';
}
