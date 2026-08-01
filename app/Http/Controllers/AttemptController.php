<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AttemptController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_text' => 'required_without:code|nullable|string',
            'code' => 'required_without:answer_text|nullable|string',
            'passed' => 'nullable|boolean',
        ]);

        $question = Question::findOrFail($request->question_id);

        // DSA / code submission path
        if ($request->has('code')) {
            $attempt = Attempt::create([
                'user_id' => $request->user()->id,
                'question_id' => $request->question_id,
                'code' => $request->code,
                'passed' => $request->boolean('passed'),
            ]);

            return response()->json($attempt->fresh());
        }

        // HR / text-answer path (unchanged)
        $attempt = Attempt::create([
            'user_id' => $request->user()->id,
            'question_id' => $request->question_id,
            'answer_text' => $request->answer_text,
        ]);

      $aiResponse = Http::timeout(60)->post(env('AI_SERVICE_URL', 'https://crackit-ai-f6tu.onrender.com') . '/ai/evaluate-answer', [
            'question' => $question->content,
            'answer' => $request->answer_text,
        ]);

        if ($aiResponse->successful()) {
            $data = $aiResponse->json();
            $attempt->update([
                'ai_score' => $data['score'] ?? null,
                'ai_feedback' => $data['feedback'] ?? null,
            ]);
        }

        return response()->json($attempt->fresh());
    }

    public function index(Request $request)
    {
        $attempts = Attempt::where('user_id', $request->user()->id)
            ->with('question')
            ->latest()
            ->get();

        return response()->json($attempts);
    }
}