<?php

namespace Tests\Feature\Jobs;

use App\Enums\ExperimentStatus;
use App\Jobs\RunExperiment;
use App\Models\Experiment;
use App\Services\ExperimentOrchestrator;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class RunExperimentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_completes_with_fake_provider_without_http_requests(): void
    {
        Http::preventStrayRequests();
        $experiment = Experiment::factory()->create(['max_turns' => 1]);
        $job = new RunExperiment($experiment->id);

        $job->handle(app(ExperimentOrchestrator::class));

        $this->assertSame(ExperimentStatus::Completed, $experiment->refresh()->status);
        $this->assertSame(2, $experiment->messages()->count());
    }

    public function test_job_exposes_unique_and_overlap_protection_for_experiment(): void
    {
        $job = new RunExperiment(42);

        $middleware = $job->middleware();

        $this->assertSame('42', $job->uniqueId());
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame(4, $job->tries);
        $this->assertSame(900, $job->timeout);
    }

    public function test_job_dispatches_the_next_turn_while_the_manual_batch_has_capacity(): void
    {
        Http::preventStrayRequests();
        Queue::fake([RunExperiment::class]);
        $experiment = Experiment::factory()->create(['max_turns' => null]);
        $job = new RunExperiment($experiment->id, 2);

        $job->handle(app(ExperimentOrchestrator::class));

        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Running, $experiment->status);
        $this->assertSame(1, $experiment->current_turn);
        Queue::assertPushed(
            RunExperiment::class,
            fn (RunExperiment $nextJob): bool => $nextJob->experimentId === $experiment->id
                && $nextJob->remainingTurns === 1,
        );
    }

    public function test_last_job_in_manual_batch_pauses_an_unlimited_experiment(): void
    {
        Http::preventStrayRequests();
        Queue::fake([RunExperiment::class]);
        $experiment = Experiment::factory()->create(['max_turns' => null]);
        $job = new RunExperiment($experiment->id);

        $job->handle(app(ExperimentOrchestrator::class));

        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Paused, $experiment->status);
        $this->assertSame(1, $experiment->current_turn);
        $this->assertNull($experiment->queued_at);
        $this->assertNull($experiment->failure_code);
        Queue::assertNothingPushed();
    }

    public function test_stale_job_exits_after_an_experiment_has_already_failed(): void
    {
        Http::preventStrayRequests();
        Queue::fake([RunExperiment::class]);
        $experiment = Experiment::factory()->create([
            'status' => ExperimentStatus::Failed,
            'failure_code' => 'malformed_json',
            'failure_message' => 'The AI provider returned an invalid response.',
        ]);
        $job = new RunExperiment($experiment->id, 12);

        $job->handle(app(ExperimentOrchestrator::class));

        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Failed, $experiment->status);
        $this->assertSame('malformed_json', $experiment->failure_code);
        $this->assertSame(0, $experiment->current_turn);
        Queue::assertNothingPushed();
    }

    public function test_overlapping_job_is_released_instead_of_discarded(): void
    {
        $job = (new RunExperiment(42))->withFakeQueueInteractions();
        $middleware = $job->middleware()[0];
        $lock = Cache::lock($middleware->getLockKey($job), 960);
        $this->assertTrue($lock->get());

        try {
            $middleware->handle(
                $job,
                fn () => $this->fail('The overlapping job should not run.'),
            );

            $job->assertReleased(30);
        } finally {
            $lock->release();
        }
    }

    public function test_terminal_queue_failure_stores_only_sanitized_details(): void
    {
        $experiment = Experiment::factory()->running()->create();
        $job = new RunExperiment($experiment->id);

        $job->failed(new RuntimeException('credential=must-never-be-stored'));

        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Failed, $experiment->status);
        $this->assertSame('queue_job_failed', $experiment->failure_code);
        $this->assertSame(
            'The queued experiment stopped before it completed.',
            $experiment->failure_message,
        );
        $this->assertStringNotContainsString(
            'credential',
            $experiment->failure_message,
        );
    }

    public function test_command_dispatches_requested_experiment(): void
    {
        Queue::fake([RunExperiment::class]);
        $experiment = Experiment::factory()->create();

        $this->artisan('experiments:run', ['experiment' => $experiment->id])
            ->expectsOutput('Experiment dispatched.')
            ->assertSuccessful();

        Queue::assertPushed(
            RunExperiment::class,
            fn (RunExperiment $job): bool => $job->experimentId === $experiment->id,
        );
    }

    public function test_command_fails_for_missing_experiment(): void
    {
        Queue::fake([RunExperiment::class]);

        $this->artisan('experiments:run', ['experiment' => 999999])
            ->expectsOutput('Experiment not found.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }
}
