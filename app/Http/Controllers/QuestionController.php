<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * GET /questions?type=dsa&topic=array&difficulty=easy
     */
    public function index(Request $request)
    {
        $query = Question::query();

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

        $questions = $query->select('id', 'title', 'type', 'topic', 'difficulty')->get();

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
     * Returns question counts grouped by type and topic — used to build
     * the "All topics / DSA / Core subjects / HR interview" filter row
     * on the unified Problems page.
     */
    public function topics()
    {
        $counts = Question::selectRaw('type, topic, COUNT(*) as count')
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

        $question = Question::create($request->only(['title', 'type', 'topic', 'difficulty', 'tags', 'content']));

        return response()->json($question, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $question = Question::with('testCases')->findOrFail($id);

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
}