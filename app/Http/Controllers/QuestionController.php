<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use App\Models\Attempt;

class QuestionController extends Controller
{
    /**
     * GET /questions?type=dsa&topic=array&difficulty=easy&status=all
     * By default only returns approved questions (so AI-generated drafts
     * never leak to students). Pass status=all (admin panel) to see everything,
     * or status=draft to see only drafts.
     */
    public function index(Request $request)
    {
        $query = Question::query();

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        } elseif (!$request->has('status')) {
            $query->where('status', 'approved');
        }
        // status=all -> no status filter applied, returns everything

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('topic')) {
            $query->where('topic', $request->topic);
        }

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->has('tag')) {
            $query->where('tags', 'like', '%' . $request->tag . '%');
        }

        $questions = $query->select('id', 'title', 'type', 'topic', 'difficulty', 'status')->get();

        $userId = $request->user()->id;
        $solvedIds = \App\Models\Attempt::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('passed', true)->orWhereNotNull('answer_text');
            })
            ->pluck('question_id')
            ->unique();

        $questions->transform(function ($q) use ($solvedIds) {
            $q->solved = $solvedIds->contains($q->id);
            return $q;
        });

        return response()->json($questions);
    }

    /**
     * GET /topics
     * Returns question counts grouped by type and topic (approved only) —
     * used to build the "All topics / DSA / Core subjects / HR interview"
     * filter row on the unified Problems page.
     */
    public function topics()
    {
        $counts = Question::where('status', 'approved')
            ->selectRaw('type, topic, COUNT(*) as count')
            ->groupBy('type', 'topic')
            ->get();

        $byType = [];
        foreach ($counts as $row) {
            $byType[$row->type]['total'] = ($byType[$row->type]['total'] ?? 0) + $row->count;
            if ($row->topic) {
                $byType[$row->type]['topics'][] = [
                    'topic' => $row->topic,
                    'count' => $row->count,
                ];
            }
        }

        return response()->json($byType);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string',
            'type' => 'required|in:dsa,hr,system_design,core_subject',
            'topic' => 'nullable|string',
            'difficulty' => 'required|in:easy,medium,hard',
            'tags' => 'nullable|string',
            'content' => 'required|string',
        ]);

        $question = Question::create([
            ...$request->only(['title', 'type', 'topic', 'difficulty', 'tags', 'content']),
            'status' => 'approved', // manually created questions are approved immediately
        ]);

        return response()->json($question, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request)
    {
        $question = Question::with('testCases')->findOrFail($id);

        if ($question->status !== 'approved' && $request->user()->role !== 'admin') {
            abort(404, 'Question not found.');
        }

        return response()->json($question);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $question = Question::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|nullable|string',
            'type' => 'sometimes|in:dsa,hr,system_design,core_subject',
            'topic' => 'sometimes|nullable|string',
            'difficulty' => 'sometimes|in:easy,medium,hard',
            'tags' => 'nullable|string',
            'content' => 'sometimes|string',
        ]);

        $question->update($request->only(['title', 'type', 'topic', 'difficulty', 'tags', 'content']));

        return response()->json($question);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json(['message' => 'Question deleted successfully']);
    }

    public function hint(string $id)
    {
        $question = Question::findOrFail($id);

        if ($question->ai_hint) {
            return response()->json($question->ai_hint);
        }

        $aiResponse = \Illuminate\Support\Facades\Http::timeout(60)->post(
            env('AI_SERVICE_URL', 'https://crackit-ai-f6tu.onrender.com') . '/ai/generate-hint',
            ['question' => $question->content]
        );

        if (!$aiResponse->successful()) {
            return response()->json([
                'error' => 'Failed to generate hint',
                'status' => $aiResponse->status(),
                'body' => $aiResponse->body(),
            ], 502);
        }

        $hint = $aiResponse->json();
        $question->update(['ai_hint' => $hint]);

        return response()->json($hint);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:dsa,hr,system_design,core_subject',
            'topic' => 'required|string',
            'difficulty' => 'required|in:easy,medium,hard',
        ]);

        $aiResponse = \Illuminate\Support\Facades\Http::timeout(90)->post(
            env('AI_SERVICE_URL', 'https://crackit-ai-f6tu.onrender.com') . '/ai/generate-question',
            [
                'type' => $request->type,
                'topic' => $request->topic,
                'difficulty' => $request->difficulty,
            ]
        );

        if (!$aiResponse->successful()) {
            return response()->json([
                'error' => 'Failed to generate question',
                'status' => $aiResponse->status(),
                'body' => $aiResponse->body(),
            ], 502);
        }

        $data = $aiResponse->json();

        if (isset($data['error'])) {
            return response()->json($data, 502);
        }

        $question = Question::create([
            'title' => $data['title'] ?? null,
            'type' => $request->type,
            'topic' => $request->topic,
            'difficulty' => $request->difficulty,
            'tags' => $data['tags'] ?? null,
            'content' => $data['content'] ?? null,
            'starter_code' => $data['starter_code'] ?? null,
            'brute_force_solution' => $data['brute_force_solution'] ?? null,
            'optimal_solution' => $data['optimal_solution'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($data['test_cases'] ?? [] as $tc) {
            $question->testCases()->create([
                'input' => explode("\n", $tc['stdin']),
                'expected_output' => $tc['expected_output'],
                'is_hidden' => false,
                'label' => $tc['label'] ?? null,
            ]);
        }

        return response()->json([
            'question' => $question->load('testCases'),
            'self_validated' => $data['self_validated'] ?? false,
            'time_complexity' => $data['time_complexity'] ?? null,
            'space_complexity' => $data['space_complexity'] ?? null,
        ], 201);
    }

    public function approve(string $id)
    {
        $question = Question::findOrFail($id);
        $question->update(['status' => 'approved']);

        return response()->json($question);
    }

    /**
     * GET /practice/recommended?type=dsa
     * Recommends the next question using a rule-based adaptive system:
     * - Prioritizes topics the user is weakest in (or has never tried)
     * - Adjusts difficulty up/down based on the user's last 3 attempts in that type
     * - Skips questions already solved
     */
    public function recommended(Request $request)
    {
        $request->validate([
            'type' => 'required|in:dsa,hr,system_design,core_subject',
        ]);

        $userId = $request->user()->id;
        $type = $request->type;

        // 1. Get all approved questions of this type, grouped by topic
        $questionsByTopic = Question::where('type', $type)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'approved');
            })
            ->get()
            ->groupBy('topic');

        if ($questionsByTopic->isEmpty()) {
            return response()->json(['error' => 'No questions available for this type yet.'], 404);
        }

        // 2. Compute per-topic accuracy from the user's attempts in this type
        $attempts = Attempt::where('user_id', $userId)
            ->whereHas('question', fn($q) => $q->where('type', $type))
            ->with('question')
            ->get();

        $topicStats = [];
        foreach ($questionsByTopic as $topic => $questions) {
            $topicAttempts = $attempts->filter(fn($a) => $a->question->topic === $topic);
            $solvedIds = $topicAttempts
                ->filter(fn($a) => $a->passed === true || ($a->ai_score !== null && $a->ai_score >= 60))
                ->pluck('question_id')
                ->unique();

            $accuracy = $topicAttempts->count() > 0
                ? ($solvedIds->count() / $topicAttempts->unique('question_id')->count()) * 100
                : null; // null = never attempted, treated as highest priority

            $topicStats[$topic] = [
                'accuracy' => $accuracy,
                'attempt_count' => $topicAttempts->count(),
                'solved_ids' => $solvedIds,
            ];
        }

        // 3. Pick the weakest topic: never-attempted topics first, then lowest accuracy
        $weakestTopic = collect($topicStats)
            ->sortBy(fn($stats) => $stats['accuracy'] ?? -1) // null (never tried) sorts first
            ->keys()
            ->first();

        // 4. Decide difficulty based on the user's last 3 attempts in this topic
        $recentInTopic = $attempts
            ->filter(fn($a) => $a->question->topic === $weakestTopic)
            ->sortByDesc('created_at')
            ->take(3);

        $recentPassed = $recentInTopic->filter(
            fn($a) => $a->passed === true || ($a->ai_score !== null && $a->ai_score >= 60)
        )->count();

        $difficultyOrder = ['easy', 'medium', 'hard'];
        if ($recentInTopic->isEmpty()) {
            $targetDifficulty = 'easy';
        } elseif ($recentPassed >= 2) {
            // doing well — bump difficulty
            $lastDifficulty = $recentInTopic->first()->question->difficulty;
            $idx = array_search($lastDifficulty, $difficultyOrder);
            $targetDifficulty = $difficultyOrder[min($idx + 1, 2)];
        } elseif ($recentPassed === 0) {
            // struggling — drop difficulty
            $lastDifficulty = $recentInTopic->first()->question->difficulty;
            $idx = array_search($lastDifficulty, $difficultyOrder);
            $targetDifficulty = $difficultyOrder[max($idx - 1, 0)];
        } else {
            $targetDifficulty = $recentInTopic->first()->question->difficulty;
        }

        // 5. Find an unsolved question in that topic + difficulty (fallback: any difficulty in that topic)
        $solvedIds = $topicStats[$weakestTopic]['solved_ids'];

        $candidate = $questionsByTopic[$weakestTopic]
            ->where('difficulty', $targetDifficulty)
            ->whereNotIn('id', $solvedIds)
            ->first();

        if (!$candidate) {
            $candidate = $questionsByTopic[$weakestTopic]
                ->whereNotIn('id', $solvedIds)
                ->first();
        }

        if (!$candidate) {
            return response()->json(['error' => 'You have solved everything available in your weakest topic — great job!'], 404);
        }

        return response()->json([
            'question_id' => $candidate->id,
            'topic' => $weakestTopic,
            'difficulty' => $candidate->difficulty,
            'reason' => $topicStats[$weakestTopic]['accuracy'] === null
                ? "You haven't tried \"$weakestTopic\" yet"
                : "Your accuracy in \"$weakestTopic\" is " . round($topicStats[$weakestTopic]['accuracy']) . "%",
        ]);
    }
}
