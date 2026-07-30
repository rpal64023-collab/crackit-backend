<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateCodeJob;
use App\Models\Evaluation;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    // POST /evaluations — returns immediately, work happens in background
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'code' => ['required', 'string'],
        ]);

        $evaluation = Evaluation::create([
            'user_id' => $request->user()?->id,
            'question_id' => $validated['question_id'],
            'code' => $validated['code'],
            'status' => 'pending',
        ]);

        EvaluateCodeJob::dispatch($evaluation);

        // 202 Accepted: request understood, work is happening, not done yet
        return response()->json([
            'evaluation_id' => $evaluation->id,
            'status' => $evaluation->status,
        ], 202);
    }

    // GET /evaluations/{id} — frontend polls this until status is completed/failed
    public function show(Evaluation $evaluation)
    {
        return response()->json([
            'evaluation_id' => $evaluation->id,
            'status' => $evaluation->status,
            'result' => $evaluation->result,
            'error' => $evaluation->error,
        ]);
    }
}