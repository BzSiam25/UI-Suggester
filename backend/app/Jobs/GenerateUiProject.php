<?php

namespace App\Jobs;

use App\Models\UiGeneration;
use App\Services\Generator\GenerationCancelled;
use App\Services\Generator\ProjectGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one generation on the queue worker. A CPU-bound local model needs
 * minutes per screen, far longer than a web request should stay open, so the
 * browser polls the generation's status while this works.
 */
class GenerateUiProject implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public int $generationId) {}

    public function handle(ProjectGenerator $generator): void
    {
        $generation = UiGeneration::find($this->generationId);
        if (! $generation || $generation->status === 'done') {
            return;
        }

        try {
            $generator->run($generation);
        } catch (GenerationCancelled) {
            return;
        } catch (Throwable $e) {
            Log::warning('UI project generation failed', ['id' => $this->generationId, 'error' => $e->getMessage()]);
            if (UiGeneration::whereKey($this->generationId)->exists()) {
                $generation->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
                $generation->report('Stopped: '.$e->getMessage());
            }
        }
    }
}
