<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestCase extends Model
{
    protected $fillable = [
        'question_id',
        'input',
        'expected_output',
        'is_hidden',
        'label',
    ];

    protected $casts = [
        'input' => 'array',
        'is_hidden' => 'boolean',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
