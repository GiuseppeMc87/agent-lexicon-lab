import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenText,
    Clock3,
    History,
    MessageSquareText,
    Play,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import ExperimentUpdateController from '@/actions/App/Http/Controllers/ExperimentUpdateController';
import RunExperimentController from '@/actions/App/Http/Controllers/RunExperimentController';
import { index } from '@/actions/App/Http/Controllers/ExperimentController';
import { ExperimentStatusBadge } from '@/components/experiment-status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type {
    Experiment,
    ExperimentMessage,
    VocabularyEntry,
    VocabularyEvent,
} from '@/types';

type Cursor = {
    message_id: number;
    event_id: number;
};

type Props = {
    experiment: Experiment;
    messages: ExperimentMessage[];
    vocabulary: VocabularyEntry[];
    events: VocabularyEvent[];
    cursor: Cursor;
    poll_interval_ms: number;
};

type UpdatePayload = {
    experiment: Experiment;
    messages: ExperimentMessage[];
    vocabulary: VocabularyEntry[];
    events: VocabularyEvent[];
    cursor: Cursor;
};

export default function ExperimentShow({
    experiment,
    messages,
    vocabulary,
    events,
    cursor,
    poll_interval_ms: pollIntervalMs,
}: Props) {
    const [currentExperiment, setCurrentExperiment] = useState(experiment);
    const [currentMessages, setCurrentMessages] = useState(messages);
    const [currentVocabulary, setCurrentVocabulary] = useState(vocabulary);
    const [currentEvents, setCurrentEvents] = useState(events);
    const [currentCursor, setCurrentCursor] = useState(cursor);
    const [pollingError, setPollingError] = useState<string | null>(null);
    const [isPolling, setIsPolling] = useState(false);

    const shouldPoll =
        currentExperiment.status === 'running' ||
        (currentExperiment.status === 'pending' &&
            currentExperiment.queued_at !== null);

    useEffect(() => {
        if (!shouldPoll) {
            setIsPolling(false);

            return;
        }

        const controller = new AbortController();
        let timer: number | undefined;

        const poll = async () => {
            setIsPolling(true);

            try {
                const response = await fetch(
                    ExperimentUpdateController.url(currentExperiment.id, {
                        query: {
                            after_message_id: currentCursor.message_id,
                            after_event_id: currentCursor.event_id,
                        },
                    }),
                    {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error(`Polling returned ${response.status}`);
                }

                const update = (await response.json()) as UpdatePayload;
                setCurrentExperiment(update.experiment);
                setCurrentMessages((existing) => [
                    ...existing,
                    ...update.messages.filter(
                        (incoming) =>
                            !existing.some(
                                (message) => message.id === incoming.id,
                            ),
                    ),
                ]);
                setCurrentEvents((existing) => [
                    ...existing,
                    ...update.events.filter(
                        (incoming) =>
                            !existing.some((event) => event.id === incoming.id),
                    ),
                ]);
                setCurrentVocabulary(update.vocabulary);
                setCurrentCursor(update.cursor);
                setPollingError(null);
            } catch (error) {
                if (!controller.signal.aborted) {
                    setPollingError(
                        error instanceof Error
                            ? 'Live updates are temporarily unavailable. Retrying automatically.'
                            : 'Live updates are temporarily unavailable.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setIsPolling(false);
                    timer = window.setTimeout(poll, pollIntervalMs);
                }
            }
        };

        timer = window.setTimeout(poll, pollIntervalMs);

        return () => {
            controller.abort();

            if (timer !== undefined) {
                window.clearTimeout(timer);
            }
        };
    }, [
        currentCursor.event_id,
        currentCursor.message_id,
        currentExperiment.id,
        pollIntervalMs,
        shouldPoll,
    ]);

    const progress = Math.round(
        (currentExperiment.current_turn / currentExperiment.max_turns) * 100,
    );

    return (
        <>
            <Head title={currentExperiment.name} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Link
                    href={index()}
                    className="inline-flex w-fit items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    All experiments
                </Link>

                <header className="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center gap-3">
                                <ExperimentStatusBadge
                                    status={currentExperiment.status}
                                />
                                <span className="text-xs text-muted-foreground">
                                    {currentExperiment.provider} ·{' '}
                                    {currentExperiment.model}
                                </span>
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                    {currentExperiment.name}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Turn {currentExperiment.current_turn} of{' '}
                                    {currentExperiment.max_turns}
                                </p>
                            </div>
                        </div>

                        {(currentExperiment.status === 'failed' ||
                            (currentExperiment.status === 'pending' &&
                                currentExperiment.queued_at === null)) && (
                            <Form
                                {...RunExperimentController.form(
                                    currentExperiment.id,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <div className="grid gap-2">
                                        <Button disabled={processing} size="lg">
                                            <Play aria-hidden="true" />
                                            {processing
                                                ? 'Queueing…'
                                                : currentExperiment.status ===
                                                    'failed'
                                                  ? 'Resume experiment'
                                                  : 'Start experiment'}
                                        </Button>
                                        <InputError
                                            message={errors.experiment}
                                        />
                                    </div>
                                )}
                            </Form>
                        )}
                    </div>

                    <div className="mt-6 space-y-2">
                        <div className="flex justify-between text-xs text-muted-foreground">
                            <span>Conversation progress</span>
                            <span>{progress}%</span>
                        </div>
                        <div
                            className="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-label="Conversation progress"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={progress}
                        >
                            <div
                                className="h-full rounded-full bg-primary transition-all"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>

                    <div
                        className="mt-4 flex min-h-5 items-center gap-2 text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {shouldPoll && (
                            <>
                                <RefreshCw
                                    className={`size-3.5 ${isPolling ? 'animate-spin' : ''}`}
                                    aria-hidden="true"
                                />
                                {isPolling
                                    ? 'Checking for new messages…'
                                    : 'Live monitoring active'}
                            </>
                        )}
                    </div>
                </header>

                {pollingError && (
                    <div
                        className="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                        role="alert"
                    >
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {pollingError}
                    </div>
                )}

                {currentExperiment.status === 'failed' && (
                    <div
                        className="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950"
                        role="alert"
                    >
                        <h2 className="font-semibold text-red-900 dark:text-red-100">
                            Experiment stopped safely
                        </h2>
                        <p className="mt-1 text-sm text-red-800 dark:text-red-200">
                            {currentExperiment.failure_message ??
                                'The run failed without exposing provider details.'}
                        </p>
                        {currentExperiment.failure_code && (
                            <p className="mt-2 font-mono text-xs text-red-700 dark:text-red-300">
                                {currentExperiment.failure_code}
                            </p>
                        )}
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,0.8fr)]">
                    <section
                        className="rounded-xl border bg-card p-5 shadow-sm sm:p-6"
                        aria-labelledby="conversation-title"
                    >
                        <div className="mb-6 flex items-center gap-3">
                            <MessageSquareText
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <h2
                                    id="conversation-title"
                                    className="font-semibold"
                                >
                                    Sender / Receiver conversation
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Symbolic text is shown beside its
                                    deterministic decoding.
                                </p>
                            </div>
                        </div>

                        {currentMessages.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                                Messages will appear here after the queued run
                                starts.
                            </div>
                        ) : (
                            <ol className="flex flex-col gap-4">
                                {currentMessages.map((message) => (
                                    <li
                                        key={message.id}
                                        className={`rounded-xl border p-4 ${
                                            message.role === 'sender'
                                                ? 'border-violet-200 bg-violet-50/60 dark:border-violet-900 dark:bg-violet-950/40'
                                                : 'border-cyan-200 bg-cyan-50/60 dark:border-cyan-900 dark:bg-cyan-950/40'
                                        }`}
                                    >
                                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <span className="text-sm font-semibold capitalize">
                                                {message.role}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                Turn {message.turn}
                                            </span>
                                        </div>
                                        <div className="grid gap-3 lg:grid-cols-2">
                                            <div className="rounded-lg border bg-background/70 p-3">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                    Symbolic
                                                </p>
                                                <p className="font-mono text-sm leading-6 break-words">
                                                    {message.symbolic_content}
                                                </p>
                                            </div>
                                            <div className="rounded-lg border bg-background/70 p-3">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                    Decoded
                                                </p>
                                                <p className="text-sm leading-6 break-words">
                                                    {message.decoded_content}
                                                </p>
                                            </div>
                                        </div>
                                        {message.vocabulary.length > 0 && (
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {message.vocabulary.map(
                                                    (item) => (
                                                        <span
                                                            key={`${item.symbol}-${item.version}`}
                                                            className="rounded-full border bg-background px-2 py-1 font-mono text-xs"
                                                            title={item.meaning}
                                                        >
                                                            {`[[${item.symbol}]]`}{' '}
                                                            v{item.version}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>

                    <div className="flex flex-col gap-6">
                        <section
                            className="rounded-xl border bg-card p-5 shadow-sm"
                            aria-labelledby="dictionary-title"
                        >
                            <div className="mb-5 flex items-center gap-3">
                                <BookOpenText
                                    className="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2
                                        id="dictionary-title"
                                        className="font-semibold"
                                    >
                                        Active vocabulary
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Latest immutable definition per symbol
                                    </p>
                                </div>
                            </div>

                            {currentVocabulary.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                    No symbols have been proposed.
                                </p>
                            ) : (
                                <dl className="flex flex-col gap-3">
                                    {currentVocabulary.map((entry) => (
                                        <div
                                            key={entry.id}
                                            className="rounded-lg border p-3"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <dt className="font-mono text-sm font-semibold">
                                                    {`[[${entry.symbol}]]`}
                                                </dt>
                                                <span className="text-xs text-muted-foreground">
                                                    v
                                                    {entry.current?.version ??
                                                        0}
                                                </span>
                                            </div>
                                            <dd className="mt-2 text-sm">
                                                {entry.current?.meaning}
                                            </dd>
                                            {entry.current?.description && (
                                                <dd className="mt-1 text-xs text-muted-foreground">
                                                    {entry.current.description}
                                                </dd>
                                            )}
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </section>

                        <section
                            className="rounded-xl border bg-card p-5 shadow-sm"
                            aria-labelledby="history-title"
                        >
                            <div className="mb-5 flex items-center gap-3">
                                <History
                                    className="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2
                                        id="history-title"
                                        className="font-semibold"
                                    >
                                        Vocabulary history
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Append-only creation and revision events
                                    </p>
                                </div>
                            </div>

                            {currentEvents.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                    History will appear as definitions evolve.
                                </p>
                            ) : (
                                <ol className="flex flex-col gap-4 border-l pl-4">
                                    {currentEvents.map((event) => (
                                        <li key={event.id} className="relative">
                                            <span
                                                className="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-primary"
                                                aria-hidden="true"
                                            />
                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span className="font-semibold text-foreground capitalize">
                                                    {event.type}
                                                </span>
                                                <span>{`[[${event.symbol}]]`}</span>
                                                <span>v{event.version}</span>
                                            </div>
                                            <p className="mt-1 text-sm">
                                                {event.meaning}
                                            </p>
                                            <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                                <Clock3
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                {event.proposer_role}, turn{' '}
                                                {event.turn}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

ExperimentShow.layout = {
    breadcrumbs: [
        {
            title: 'Experiments',
            href: index(),
        },
    ],
};
