<?php

namespace App\Models;

class NetworkMapPosition extends BaseModel
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_group_id',
        'x',
        'y',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
        ];
    }
}
