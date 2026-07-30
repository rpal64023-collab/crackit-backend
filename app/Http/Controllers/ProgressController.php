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

        $topicAccuracy = $attempts->groupBy(fn($a) => $a->question->type)
            ->map(fn($group) => round($group->avg('ai_score'), 1));

        // Difficulty breakdown: solved (score >= 60) vs total attempted per difficulty
        $difficultyBreakdown = [];
        foreach (['easy', 'medium', 'hard'] as $diff) {
            $inDifficulty = $attempts->filter(fn($a) => $a->question->difficulty === $diff);
            $solved = $inDifficulty->filter(fn($a) => $a->ai_score >= 60)->unique('question_id')->count();
            $total = $inDifficulty->unique('question_id')->count();
            $difficultyBreakdown[$diff] = ['solved' => $solved, 'total' => $total];
        }

        $level = 'Beginner';
        if ($avgScore >= 70 && $totalAttempts >= 50) {
            $level = 'Advanced';
        } elseif ($avgScore >= 40 && $totalAttempts >= 20) {
            $level = 'Intermediate';
        }

        // Streak calculation (existing logic)
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

        // Max streak ever (simple version: longest run of consecutive dates)
        $allDates = $dates->sort()->values();
        $maxStreak = 0;
        $current = 0;
        $prev = null;
        foreach ($allDates as $d) {
            if ($prev && \Carbon\Carbon::parse($d)->diffInDays(\Carbon\Carbon::parse($prev)) === 1) {
                $current++;
            } else {
                $current = 1;
            }
            $maxStreak = max($maxStreak, $current);
            $prev = $d;
        }

        // Activity heatmap: last 90 days, count of attempts per day
        $ninetyDaysAgo = now()->subDays(89)->startOfDay();
        $activityCounts = Attempt::where('user_id', $userId)
            ->where('created_at', '>=', $ninetyDaysAgo)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $heatmap = [];
        for ($i = 89; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $heatmap[] = ['date' => $date, 'count' => $activityCounts[$date] ?? 0];
        }

        // Recent activity: last 5 attempts
        $recentActivity = Attempt::where('user_id', $userId)
            ->with('question')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($a) => [
                'title' => $a->question->title ?? "Question #{$a->question_id}",
                'score' => $a->ai_score,
                'passed' => $a->ai_score !== null && $a->ai_score >= 60,
                'time' => $a->created_at->diffForHumans(),
            ]);

        return response()->json([
            'total_attempts' => $totalAttempts,
            'average_score' => $avgScore,
            'topic_accuracy' => $topicAccuracy,
            'level' => $level,
            'day_streak' => $streak,
            'max_streak' => $maxStreak,
            'difficulty_breakdown' => $difficultyBreakdown,
            'activity_heatmap' => $heatmap,
            'recent_activity' => $recentActivity,
        ]);
    }
}
