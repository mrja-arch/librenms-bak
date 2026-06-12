<?php

namespace App\Enums;

enum OperationTaskType: string
{
    case Scan = 'scan';
    case Discover = 'discover';
    case Poll = 'poll';
    case Ping = 'ping';
}
