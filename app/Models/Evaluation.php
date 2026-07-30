<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'code',
        'status',
        'result',
        'error',
    ];

    protected $casts = [
        'result' => 'array',
    ];
}