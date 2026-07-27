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