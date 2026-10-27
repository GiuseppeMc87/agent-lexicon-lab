import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, FlaskConical, Plus } from 'lucide-react';
import {
    index,
    show,
    store,
} from '@/actions/App/Http/Controllers/ExperimentController';
import { ExperimentStatusBadge } from '@/components/experiment-status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Experiment, Paginated } from '@/types';

type Props = {
    experiments: Paginated<Experiment>;
    limits: {
        default_turns: number;
        maximum_turns: number;
    };
    default_instructions: {
        sender: string;
        receiver: string;
    };
    execution: {
        provider: string;
        paid_enabled: boolean;
    };
};

export default function ExperimentIndex({
    experiments,
    limits,
    default_instructions: defaultInstructions,
    execution,
}: Props) {
    return (
        <>
            <Head title="Experiments" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-primary">
                            Agent Lexicon Lab
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Vocabulary experiments
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Observe two agents build and revise a shared
                            symbolic language over a bounded conversation.
                        </p>
                    </div>
                    <div className="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                        <FlaskConical className="size-4" aria-hidden="true" />
                        Provider: {execution.provider}
                    </div>
                </header>

                {execution.provider === 'vertex' && !execution.paid_enabled && (
                    <div
                        className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                        role="status"
                    >
                        Vertex AI execution is disabled. Experiments can be
                        prepared, but paid runs require the explicit environment
                        feature flag.
                    </div>
                )}

                <section className="rounded-xl border bg-card p-5 shadow-sm">
                    <div className="mb-5 space-y-1">
                        <h2 className="text-lg font-semibold">
                            Create an experiment
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Creating a record does not start AI execution.
                        </p>
                    </div>

                    <Form
                        {...store.form()}
                        resetOnSuccess
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 md:grid-cols-[1fr_12rem_auto] md:items-end">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Experiment name
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            maxLength={120}
                                            placeholder="Negotiating navigation signals"
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="max_turns">Turns</Label>
                                        <select
                                            id="max_turns"
                                            name="max_turns"
                                            defaultValue={limits.default_turns}
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                            aria-invalid={Boolean(
                                                errors.max_turns,
                                            )}
                                        >
                                            {Array.from(
                                                {
                                                    length: limits.maximum_turns,
                                                },
                                                (_, indexValue) =>
                                                    indexValue + 1,
                                            ).map((turns) => (
                                                <option
                                                    key={turns}
                                                    value={turns}
                                                >
                                                    {turns}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.max_turns}
                                        />
                                    </div>

                                    <Button disabled={processing}>
                                        <Plus aria-hidden="true" />
                                        {processing ? 'Creating…' : 'Create'}
                                    </Button>
                                </div>

                                <details className="group rounded-xl border bg-muted/20 open:bg-muted/30">
                                    <summary className="cursor-pointer list-none px-4 py-3 text-sm font-medium marker:hidden">
                                        <span className="flex items-center justify-between gap-3">
                                            Agent instructions
                                            <span className="text-xs font-normal text-muted-foreground">
                                                Optional
                                            </span>
                                        </span>
                                    </summary>
                                    <div className="grid gap-4 border-t p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="sender_instructions">
                                                Sender brief
                                            </Label>
                                            <textarea
                                                id="sender_instructions"
                                                name="sender_instructions"
                                                rows={4}
                                                maxLength={2000}
                                                defaultValue={
                                                    defaultInstructions.sender
                                                }
                                                placeholder="Example: Introduce spatial concepts gradually."
                                                className="min-h-28 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                aria-invalid={Boolean(
                                                    errors.sender_instructions,
                                                )}
                                            />
                                            <p className="text-xs leading-5 text-muted-foreground">
                                                Guides what the sender tries to
                                                express.
                                            </p>
                                            <InputError
                                                message={
                                                    errors.sender_instructions
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="receiver_instructions">
                                                Receiver brief
                                            </Label>
                                            <textarea
                                                id="receiver_instructions"
                                                name="receiver_instructions"
                                                rows={4}
                                                maxLength={2000}
                                                defaultValue={
                                                    defaultInstructions.receiver
                                                }
                                                placeholder="Example: Challenge ambiguous definitions before reusing them."
                                                className="min-h-28 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                                aria-invalid={Boolean(
                                                    errors.receiver_instructions,
                                                )}
                                            />
                                            <p className="text-xs leading-5 text-muted-foreground">
                                                Guides how the receiver
                                                interprets and replies.
                                            </p>
                                            <InputError
                                                message={
                                                    errors.receiver_instructions
                                                }
                                            />
                                        </div>

                                        <p className="text-xs leading-5 text-muted-foreground md:col-span-2">
                                            These briefs supplement the fixed
                                            experiment protocol; they cannot
                                            override symbol or response-format
                                            rules.
                                        </p>
                                    </div>
                                </details>
                            </>
                        )}
                    </Form>
                </section>

                <section aria-labelledby="experiment-list-title">
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <h2
                            id="experiment-list-title"
                            className="text-lg font-semibold"
                        >
                            Your experiments
                        </h2>
                        <span className="text-sm text-muted-foreground">
                            {experiments.meta.total} total
                        </span>
                    </div>

                    {experiments.data.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center">
                            <FlaskConical
                                className="mx-auto mb-3 size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h3 className="font-medium">No experiments yet</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Create one above, then review it before starting
                                the queued run.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {experiments.data.map((experiment) => (
                                <Link
                                    key={experiment.id}
                                    href={show(experiment.id)}
                                    prefetch
                                    className="group flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="leading-snug font-semibold">
                                            {experiment.name}
                                        </h3>
                                        <ExperimentStatusBadge
                                            status={experiment.status}
                                        />
                                    </div>
                                    <dl className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Progress
                                            </dt>
                                            <dd className="font-medium">
                                                {experiment.current_turn} /{' '}
                                                {experiment.max_turns} turns
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Vocabulary
                                            </dt>
                                            <dd className="font-medium">
                                                {experiment.vocabulary_entries_count ??
                                                    0}{' '}
                                                symbols
                                            </dd>
                                        </div>
                                    </dl>
                                    <span className="mt-auto inline-flex items-center gap-1 text-sm font-medium text-primary">
                                        Open experiment
                                        <ArrowRight
                                            className="size-4 transition-transform group-hover:translate-x-0.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}

                    {(experiments.links.prev || experiments.links.next) && (
                        <nav
                            className="mt-6 flex items-center justify-between"
                            aria-label="Experiment pagination"
                        >
                            {experiments.links.prev ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={experiments.links.prev}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                            <span className="text-sm text-muted-foreground">
                                Page {experiments.meta.current_page} of{' '}
                                {experiments.meta.last_page}
                            </span>
                            {experiments.links.next ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={experiments.links.next}
                                        preserveScroll
                                    >
                                        Next
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                        </nav>
                    )}
                </section>
            </div>
        </>
    );
}

ExperimentIndex.layout = {
    breadcrumbs: [
        {
            title: 'Experiments',
            href: index(),
        },
    ],
};
