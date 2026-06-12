<?php

namespace App\Enums;

enum OperationTaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
