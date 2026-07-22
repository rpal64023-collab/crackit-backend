<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AttemptController extends Controller
{
    /**
     * Store a new attempt (student submits an answer).
     */
    public function store(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_text' => 'required|string',
        ]);

        $question = Question::findOrFail($request->question_id);

        $attempt = Attempt::create([
            'user_id' => $request->user()->id,
            'question_id' => $request->question_id,
            'answer_text' => $request->answer_text,
        ]);

        // Call the FastAPI AI service to evaluate the answer
        $aiResponse = Http::timeout(15)->post('http://127.0.0.1:8001/ai/evaluate-answer', [
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

    /**
     * List the logged-in user's own attempts.
     */
    public function index(Request $request)
    {
        $attempts = Attempt::where('user_id', $request->user()->id)
            ->with('question')
            ->latest()
            ->get();

        return response()->json($attempts);
    }
}
