<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'topic',
        'difficulty',
        'tags',
        'content',
        'ai_hint',
        'starter_code',
        'brute_force_solution',
        'optimal_solution',
        'status',
        'company',
    ];

    protected $casts = [
        'ai_hint' => 'array',
    ];

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function testCases()
    {
        return $this->hasMany(TestCase::class);
    }
}