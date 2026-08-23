<?php

namespace App\Http\Controllers;

use App\Models\TestCase;
use Illuminate\Http\Request;

class TestCaseController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'input' => 'required|array',
            'expected_output' => 'required|string',
            'is_hidden' => 'boolean',
        ]);

        $testCase = TestCase::create($request->only(['question_id', 'input', 'expected_output', 'is_hidden']));

        return response()->json($testCase, 201);
    }

    public function index(Request $request, $questionId)
    {
        $testCases = TestCase::where('question_id', $questionId)->get();

        return response()->json($testCases);
    }
    public function update(Request $request, $id)
    {
        $testCase = TestCase::findOrFail($id);

        $request->validate([
            'input' => 'sometimes|required|array',
            'expected_output' => 'sometimes|required|string',
            'is_hidden' => 'sometimes|boolean',
            'label' => 'sometimes|nullable|string',
        ]);
        $testCase->update($request->only(['input', 'expected_output', 'is_hidden', 'label']));

        return response()->json($testCase);
    }
}
