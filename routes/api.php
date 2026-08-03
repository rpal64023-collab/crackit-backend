<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\EvaluationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/progress', [ProgressController::class, 'index']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});


Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin-test', function () {
        return response()->json(['message' => 'Welcome, admin!']);
    });
});


// Public: anyone logged in can view questions
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/topics', [QuestionController::class, 'topics']);
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);
});

// Admin only: create, update, delete questions
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/questions', [QuestionController::class, 'store']);
    Route::put('/questions/{id}', [QuestionController::class, 'update']);
    Route::delete('/questions/{id}', [QuestionController::class, 'destroy']);
    Route::post('/test-cases', [TestCaseController::class, 'store']);
    Route::post('/admin/generate-question', [QuestionController::class, 'generate']);
    Route::post('/admin/questions/{id}/approve', [QuestionController::class, 'approve']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);
    Route::post('/attempts', [AttemptController::class, 'store']);
    Route::get('/attempts', [AttemptController::class, 'index']);
    Route::get('/questions/{questionId}/test-cases', [TestCaseController::class, 'index']);
    Route::get('/questions/{id}/hint', [QuestionController::class, 'hint']);
    Route::get('/topics', [QuestionController::class, 'topics']);
});

Route::middleware('throttle:code-execution')->group(function () {
    Route::post('/evaluations', [EvaluationController::class, 'store']);
    Route::get('/evaluations/{evaluation}', [EvaluationController::class, 'show']);
});
