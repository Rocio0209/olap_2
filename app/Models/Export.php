<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = [
        'type',
        'status',
        'progress',
        'batch_id',
        'final_path',
        'params',
        'error',
    ];

    protected $casts = [
        'params' => 'array',
    ];
}