<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $attempts = Attempt::where('user_id', $userId)
            ->whereNotNull('ai_score')
            ->with('question')
            ->get();

        $totalAttempts = $attempts->count();
        $avgScore = $totalAttempts > 0 ? round($attempts->avg('ai_score'), 1) : 0;

        // Topic-wise accuracy (grouped by question type: dsa, hr, system_design)
        $topicAccuracy = $attempts->groupBy(fn($attempt) => $attempt->question->type)
            ->map(function ($group) {
                return round($group->avg('ai_score'), 1);
            });

        // Simple level logic
        $level = 'Beginner';
        if ($avgScore >= 70 && $totalAttempts >= 50) {
            $level = 'Advanced';
        } elseif ($avgScore >= 40 && $totalAttempts >= 20) {
            $level = 'Intermediate';
        }

        // Streak: count consecutive days with at least one attempt, ending today or yesterday
        $dates = Attempt::where('user_id', $userId)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date');

        $streak = 0;
        $today = now()->startOfDay();

        foreach ($dates as $date) {
            $expectedDate = $today->copy()->subDays($streak)->toDateString();
            if ($date === $expectedDate) {
                $streak++;
            } else {
                break;
            }
        }

        return response()->json([
            'total_attempts' => $totalAttempts,
            'average_score' => $avgScore,
            'topic_accuracy' => $topicAccuracy,
            'level' => $level,
            'day_streak' => $streak,
        ]);
    }
}