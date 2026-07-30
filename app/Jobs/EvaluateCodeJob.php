<?php

namespace App\Jobs;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Don't let a hung Groq call block the worker forever
    public int $timeout = 30;

    // Retry twice more if Groq is briefly down/slow
    public int $tries = 3;
    public int $backoff = 5; // seconds between retries

    public function __construct(public Evaluation $evaluation)
    {
    }

    public function handle(): void
    {
        $this->evaluation->update(['status' => 'processing']);

        try {
            $response = Http::withToken(config('services.groq.key'))
                ->timeout(25)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'openai/gpt-oss-120b',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a code reviewer. Given a solution, give short, specific feedback: correctness issues, edge cases missed, and one improvement suggestion. Keep it under 120 words.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->evaluation->code,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Groq API error: ' . $response->status() . ' ' . $response->body());
            }

            $feedback = $response->json('choices.0.message.content');

            $this->evaluation->update([
                'status' => 'completed',
                'result' => ['feedback' => $feedback],
            ]);
        } catch (Throwable $e) {
            Log::error('EvaluateCodeJob failed', [
                'evaluation_id' => $this->evaluation->id,
                'error' => $e->getMessage(),
            ]);

            // Only mark as failed after the last attempt — otherwise let it retry
            if ($this->attempts() >= $this->tries) {
                $this->evaluation->update([
                    'status' => 'failed',
                    'error' => 'Could not get AI feedback right now. Please try again.',
                ]);
            }

            throw $e; // lets Laravel's retry mechanism do its job
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->evaluation->update([
            'status' => 'failed',
            'error' => 'Could not get AI feedback right now. Please try again.',
        ]);
    }
}