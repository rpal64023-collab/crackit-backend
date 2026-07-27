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

        return response()->json(
            $query->select('id', 'title', 'type', 'topic', 'difficulty')->get()
        );
    }

    /**
     * GET /topics?type=dsa
     * Returns distinct topics for a type, with question counts — used by the Topic select page.
     */
    public function topics(Request $request)
    {
        $request->validate([
            'type' => 'required|in:dsa,hr,system_design,core_subject',
        ]);

        $topics = Question::where('type', $request->type)
            ->whereNotNull('topic')
            ->selectRaw('topic, count(*) as count')
            ->groupBy('topic')
            ->get();

        return response()->json($topics);
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
}