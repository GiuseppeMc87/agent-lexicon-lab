<?php

namespace Tests\Feature\Jobs;

use App\Enums\ExperimentStatus;
use App\Jobs\RunExperiment;
use App\Models\Experiment;
use App\Services\ExperimentOrchestrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame(1, $job->tries);
        $this->assertSame(900, $job->timeout);
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
