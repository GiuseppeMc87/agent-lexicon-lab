import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUp,
    BookOpenText,
    Clock3,
    Copy,
    History,
    MessageSquareText,
    Play,
    Settings2,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import CloneExperimentController from '@/actions/App/Http/Controllers/CloneExperimentController';
import ExperimentUpdateController from '@/actions/App/Http/Controllers/ExperimentUpdateController';
import RunExperimentController from '@/actions/App/Http/Controllers/RunExperimentController';
import UpdateExperimentInstructionsController from '@/actions/App/Http/Controllers/UpdateExperimentInstructionsController';
import { index } from '@/actions/App/Http/Controllers/ExperimentController';
import { ExperimentStatusBadge } from '@/components/experiment-status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
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

const MIN_REPLY_REVEAL_DELAY_MS = 800;
const MAX_REPLY_REVEAL_DELAY_MS = 12_000;
const DEFAULT_REPLY_REVEAL_DELAY_MS = 1_200;

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function ComposingIndicator({ role }: { role: ExperimentMessage['role'] }) {
    const isSender = role === 'sender';

    return (
        <div
            className={`flex ${isSender ? 'justify-end' : 'justify-start'}`}
            role="status"
            aria-live="polite"
        >
            <div
                className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm text-muted-foreground shadow-sm ${
                    isSender
                        ? 'rounded-tr-md bg-background'
                        : 'rounded-tl-md bg-muted/45'
                }`}
            >
                <span
                    className={`flex size-7 items-center justify-center rounded-full text-xs font-bold ${
                        isSender
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-secondary text-secondary-foreground'
                    }`}
                >
                    {isSender ? 'S' : 'R'}
                </span>
                <span className="capitalize">{role} is composing</span>
                <span className="flex items-center gap-1" aria-hidden="true">
                    <span className="size-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.3s]" />
                    <span className="size-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.15s]" />
                    <span className="size-1.5 animate-bounce rounded-full bg-current" />
                </span>
            </div>
        </div>
    );
}

function VocabularyList({ vocabulary }: { vocabulary: VocabularyEntry[] }) {
    if (vocabulary.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-6 text-center">
                <BookOpenText
                    className="mx-auto size-7 text-muted-foreground/50"
                    aria-hidden="true"
                />
                <p className="mt-3 text-sm font-medium">No vocabulary yet</p>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    New symbols will appear here as soon as either agent defines
                    them.
                </p>
            </div>
        );
    }

    return (
        <dl className="flex flex-col gap-3">
            {vocabulary.map((entry) => (
                <div
                    key={entry.id}
                    className="rounded-xl border bg-background p-3.5"
                >
                    <div className="flex items-start justify-between gap-3">
                        <dt className="font-mono text-sm font-semibold break-all">
                            {`[[${entry.symbol}]]`}
                        </dt>
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                            v{entry.current?.version ?? 0}
                        </span>
                    </div>
                    <dd className="mt-2 text-sm leading-5">
                        {entry.current?.meaning}
                    </dd>
                    {entry.current?.description && (
                        <dd className="mt-1.5 text-xs leading-5 text-muted-foreground">
                            {entry.current.description}
                        </dd>
                    )}
                </div>
            ))}
        </dl>
    );
}

function MessageDetailDialog({
    message,
    onOpenChange,
}: {
    message: ExperimentMessage | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={message !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-3xl">
                {message && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="capitalize">
                                Turn {message.turn} · {message.role}
                            </DialogTitle>
                            <DialogDescription>
                                Created at {formatTime(message.created_at)} ·{' '}
                                {message.vocabulary.length}{' '}
                                {message.vocabulary.length === 1
                                    ? 'symbol attached'
                                    : 'symbols attached'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 md:grid-cols-2">
                            <section className="rounded-xl border bg-muted/35 p-4">
                                <h3 className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                    Symbolic message
                                </h3>
                                <p className="mt-3 font-mono text-sm leading-6 break-words whitespace-pre-wrap">
                                    {message.symbolic_content}
                                </p>
                            </section>
                            <section className="rounded-xl border p-4">
                                <h3 className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                    Decoded meaning
                                </h3>
                                <p className="mt-3 text-sm leading-6 break-words whitespace-pre-wrap">
                                    {message.decoded_content}
                                </p>
                            </section>
                        </div>

                        <section>
                            <h3 className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                Vocabulary attached to this message
                            </h3>
                            {message.vocabulary.length === 0 ? (
                                <p className="mt-3 rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
                                    This message did not create or revise a
                                    symbol.
                                </p>
                            ) : (
                                <div className="mt-3 overflow-hidden rounded-xl border">
                                    {message.vocabulary.map((item) => (
                                        <div
                                            key={`${item.symbol}-${item.version}`}
                                            className="grid gap-1 border-b p-3 last:border-b-0 sm:grid-cols-[10rem_minmax(0,1fr)]"
                                        >
                                            <p className="font-mono text-sm font-semibold">
                                                {`[[${item.symbol}]]`} v
                                                {item.version}
                                            </p>
                                            <p className="text-sm leading-5 text-muted-foreground">
                                                {item.meaning}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function PromptConfigurationDialog({
    experiment,
    open,
    onOpenChange,
}: {
    experiment: Experiment;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEditable =
        experiment.status === 'pending' && experiment.queued_at === null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Agent instructions</DialogTitle>
                    <DialogDescription>
                        Give each role an optional objective for this
                        experiment. The symbolic-language protocol and
                        structured response rules remain fixed.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-xl border bg-muted/35 p-3 text-xs leading-5 text-muted-foreground">
                    Instructions are stored with the experiment for
                    reproducibility. Once a run is queued, clone it to change
                    the briefs without rewriting its history.
                </div>

                {isEditable ? (
                    <Form
                        key={`${experiment.id}-${experiment.updated_at}`}
                        {...UpdateExperimentInstructionsController.form(
                            experiment.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="dialog_sender_instructions">
                                            Sender brief
                                        </Label>
                                        <textarea
                                            id="dialog_sender_instructions"
                                            name="sender_instructions"
                                            rows={6}
                                            maxLength={2000}
                                            defaultValue={
                                                experiment.sender_instructions ??
                                                ''
                                            }
                                            placeholder="Introduce spatial concepts gradually."
                                            className="min-h-36 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                            aria-invalid={Boolean(
                                                errors.sender_instructions,
                                            )}
                                        />
                                        <InputError
                                            message={errors.sender_instructions}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="dialog_receiver_instructions">
                                            Receiver brief
                                        </Label>
                                        <textarea
                                            id="dialog_receiver_instructions"
                                            name="receiver_instructions"
                                            rows={6}
                                            maxLength={2000}
                                            defaultValue={
                                                experiment.receiver_instructions ??
                                                ''
                                            }
                                            placeholder="Challenge ambiguous definitions before reusing them."
                                            className="min-h-36 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                            aria-invalid={Boolean(
                                                errors.receiver_instructions,
                                            )}
                                        />
                                        <InputError
                                            message={
                                                errors.receiver_instructions
                                            }
                                        />
                                    </div>
                                </div>

                                <InputError message={errors.experiment} />

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save instructions'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <section className="rounded-xl border p-4">
                                <h3 className="text-sm font-semibold">
                                    Sender brief
                                </h3>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                    {experiment.sender_instructions ??
                                        'No supplementary instructions.'}
                                </p>
                            </section>
                            <section className="rounded-xl border p-4">
                                <h3 className="text-sm font-semibold">
                                    Receiver brief
                                </h3>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                    {experiment.receiver_instructions ??
                                        'No supplementary instructions.'}
                                </p>
                            </section>
                        </div>

                        <Form
                            {...CloneExperimentController.form(experiment.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Close
                                        </Button>
                                    </DialogClose>
                                    <Button disabled={processing}>
                                        <Copy aria-hidden="true" />
                                        {processing
                                            ? 'Cloning…'
                                            : 'Clone to edit'}
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

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
    const [pendingMessages, setPendingMessages] = useState<ExperimentMessage[]>(
        [],
    );
    const [pollingError, setPollingError] = useState<string | null>(null);
    const [runRequested, setRunRequested] = useState(false);
    const [runSubmissionSucceeded, setRunSubmissionSucceeded] = useState(false);
    const [selectedMessage, setSelectedMessage] =
        useState<ExperimentMessage | null>(null);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [vocabularyOpen, setVocabularyOpen] = useState(false);
    const [promptConfigurationOpen, setPromptConfigurationOpen] =
        useState(false);
    const [showJumpToLatest, setShowJumpToLatest] = useState(false);
    const conversationRef = useRef<HTMLDivElement>(null);
    const shouldFollowLatestRef = useRef(true);
    const runRequestedRef = useRef(false);
    const knownMessageIdsRef = useRef(
        new Set(messages.map((message) => message.id)),
    );
    const turnWaitStartedAtRef = useRef(Date.now());
    const replyDelayByTurnRef = useRef(new Map<number, number>());
    const lastRevealedMessageRef = useRef<ExperimentMessage | null>(
        messages[messages.length - 1] ?? null,
    );

    useEffect(() => {
        setCurrentExperiment(experiment);
        setCurrentVocabulary(vocabulary);
        setCurrentEvents(events);
        setSelectedMessage(null);
        shouldFollowLatestRef.current = true;

        if (!runRequestedRef.current) {
            setCurrentMessages(messages);
            setPendingMessages([]);
            setCurrentCursor(cursor);
            knownMessageIdsRef.current = new Set(
                messages.map((message) => message.id),
            );
            turnWaitStartedAtRef.current = Date.now();
            replyDelayByTurnRef.current.clear();
            lastRevealedMessageRef.current =
                messages[messages.length - 1] ?? null;
        }
    }, [cursor, events, experiment, messages, vocabulary]);

    const nextPendingMessage = pendingMessages[0] ?? null;

    useEffect(() => {
        if (nextPendingMessage === null) {
            return;
        }

        const previousMessage = lastRevealedMessageRef.current;
        const startsNewTurn =
            nextPendingMessage.role === 'sender' &&
            previousMessage?.role === 'receiver' &&
            nextPendingMessage.turn > previousMessage.turn;
        const previousTurnDelay =
            previousMessage === null
                ? DEFAULT_REPLY_REVEAL_DELAY_MS
                : (replyDelayByTurnRef.current.get(previousMessage.turn) ??
                  DEFAULT_REPLY_REVEAL_DELAY_MS);
        const revealDelay =
            nextPendingMessage.role === 'receiver'
                ? (replyDelayByTurnRef.current.get(nextPendingMessage.turn) ??
                  DEFAULT_REPLY_REVEAL_DELAY_MS)
                : startsNewTurn
                  ? previousTurnDelay
                  : 0;

        const timer = window.setTimeout(() => {
            setCurrentMessages((existing) => {
                if (
                    existing.some(
                        (message) => message.id === nextPendingMessage.id,
                    )
                ) {
                    return existing;
                }

                return [...existing, nextPendingMessage].sort(
                    (first, second) =>
                        first.turn === second.turn
                            ? first.id - second.id
                            : first.turn - second.turn,
                );
            });
            lastRevealedMessageRef.current = nextPendingMessage;
            setPendingMessages((existing) =>
                existing.filter(
                    (message) => message.id !== nextPendingMessage.id,
                ),
            );
        }, revealDelay);

        return () => window.clearTimeout(timer);
    }, [nextPendingMessage]);

    const expectedMessageCount =
        currentExperiment.messages_count ?? currentMessages.length;
    const hasUnfetchedMessages = currentMessages.length < expectedMessageCount;
    const shouldPoll =
        runRequested ||
        hasUnfetchedMessages ||
        currentExperiment.status === 'running' ||
        (currentExperiment.status === 'pending' &&
            currentExperiment.queued_at !== null);
    const composingRole =
        nextPendingMessage?.role ?? (shouldPoll ? 'sender' : null);

    useEffect(() => {
        const isFinished =
            currentExperiment.status === 'completed' ||
            currentExperiment.status === 'failed';

        if (
            runRequested &&
            runSubmissionSucceeded &&
            isFinished &&
            !hasUnfetchedMessages
        ) {
            runRequestedRef.current = false;
            setRunRequested(false);
        }
    }, [
        currentExperiment.status,
        hasUnfetchedMessages,
        runRequested,
        runSubmissionSucceeded,
    ]);

    useEffect(() => {
        if (!shouldPoll) {
            return;
        }

        const controller = new AbortController();
        let timer: number | undefined;
        const pollingDelay = Math.min(pollIntervalMs, 1000);

        const poll = async () => {
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
                const incomingMessages = update.messages.filter(
                    (message) => !knownMessageIdsRef.current.has(message.id),
                );

                if (incomingMessages.length > 0) {
                    const receivedAt = Date.now();

                    for (const message of incomingMessages) {
                        if (message.role === 'sender') {
                            const measuredWait =
                                receivedAt - turnWaitStartedAtRef.current;
                            replyDelayByTurnRef.current.set(
                                message.turn,
                                Math.min(
                                    MAX_REPLY_REVEAL_DELAY_MS,
                                    Math.max(
                                        MIN_REPLY_REVEAL_DELAY_MS,
                                        measuredWait,
                                    ),
                                ),
                            );
                            turnWaitStartedAtRef.current = receivedAt;
                        }

                        knownMessageIdsRef.current.add(message.id);
                    }

                    setPendingMessages((existing) =>
                        [...existing, ...incomingMessages].sort(
                            (first, second) =>
                                first.turn === second.turn
                                    ? first.id - second.id
                                    : first.turn - second.turn,
                        ),
                    );
                }
                setCurrentEvents((existing) => {
                    const knownIds = new Set(existing.map((event) => event.id));

                    return [
                        ...existing,
                        ...update.events.filter(
                            (event) => !knownIds.has(event.id),
                        ),
                    ];
                });
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
                    timer = window.setTimeout(poll, pollingDelay);
                }
            }
        };

        void poll();

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

    useEffect(() => {
        const conversation = conversationRef.current;

        if (!conversation || !shouldFollowLatestRef.current) {
            return;
        }

        conversation.scrollTo({
            top: 0,
            behavior:
                currentMessages.length === messages.length ? 'auto' : 'smooth',
        });
    }, [currentMessages.length, messages.length]);

    const groupedMessages = useMemo(() => {
        const groups = new Map<number, ExperimentMessage[]>();

        for (const message of currentMessages) {
            groups.set(message.turn, [
                ...(groups.get(message.turn) ?? []),
                message,
            ]);
        }

        return Array.from(groups.entries())
            .sort(([firstTurn], [secondTurn]) => secondTurn - firstTurn)
            .map(
                ([turn, turnMessages]) =>
                    [
                        turn,
                        [...turnMessages].sort(
                            (first, second) => second.id - first.id,
                        ),
                    ] as const,
            );
    }, [currentMessages]);

    const progress = Math.round(
        (currentExperiment.current_turn / currentExperiment.max_turns) * 100,
    );

    const handleConversationScroll = () => {
        const conversation = conversationRef.current;

        if (!conversation) {
            return;
        }

        const isNearTop = conversation.scrollTop < 96;
        shouldFollowLatestRef.current = isNearTop;
        setShowJumpToLatest(!isNearTop);
    };

    const jumpToLatest = () => {
        const conversation = conversationRef.current;

        if (!conversation) {
            return;
        }

        shouldFollowLatestRef.current = true;
        setShowJumpToLatest(false);
        conversation.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    return (
        <>
            <Head title={currentExperiment.name} />

            <div className="flex w-full flex-1 flex-col gap-4 p-3 sm:p-5 lg:h-[calc(100dvh-4rem)] lg:min-h-0 lg:p-6">
                <header className="shrink-0 rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="min-w-0">
                            <Link
                                href={index()}
                                className="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground transition hover:text-foreground"
                            >
                                <ArrowLeft
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                All experiments
                            </Link>
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <ExperimentStatusBadge
                                    status={currentExperiment.status}
                                />
                                <span className="text-xs text-muted-foreground">
                                    {currentExperiment.provider} ·{' '}
                                    {currentExperiment.model}
                                </span>
                            </div>
                            <h1 className="mt-2 truncate text-xl font-semibold tracking-tight sm:text-2xl">
                                {currentExperiment.name}
                            </h1>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <div
                                className="inline-flex h-9 items-center gap-2 rounded-lg border bg-muted/35 px-3 text-xs font-medium text-muted-foreground"
                                aria-live="polite"
                            >
                                <span
                                    className={`size-2 rounded-full ${
                                        shouldPoll
                                            ? 'animate-pulse bg-foreground'
                                            : 'bg-muted-foreground/40'
                                    }`}
                                />
                                {shouldPoll ? 'Live updates on' : 'Up to date'}
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPromptConfigurationOpen(true)}
                            >
                                <Settings2 aria-hidden="true" />
                                Agent instructions
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setHistoryOpen(true)}
                            >
                                <History aria-hidden="true" />
                                History
                            </Button>

                            {(currentExperiment.status === 'failed' ||
                                (currentExperiment.status === 'pending' &&
                                    currentExperiment.queued_at === null)) && (
                                <Form
                                    {...RunExperimentController.form(
                                        currentExperiment.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    onStart={() => {
                                        runRequestedRef.current = true;
                                        turnWaitStartedAtRef.current =
                                            Date.now();
                                        replyDelayByTurnRef.current.clear();
                                        setRunRequested(true);
                                        setRunSubmissionSucceeded(false);
                                    }}
                                    onSuccess={() =>
                                        setRunSubmissionSucceeded(true)
                                    }
                                    onCancel={() => {
                                        runRequestedRef.current = false;
                                        setRunRequested(false);
                                        setRunSubmissionSucceeded(false);
                                    }}
                                    onError={() => {
                                        runRequestedRef.current = false;
                                        setRunRequested(false);
                                        setRunSubmissionSucceeded(false);
                                    }}
                                    onHttpException={() => {
                                        runRequestedRef.current = false;
                                        setRunRequested(false);
                                        setRunSubmissionSucceeded(false);
                                    }}
                                    onNetworkError={() => {
                                        runRequestedRef.current = false;
                                        setRunRequested(false);
                                        setRunSubmissionSucceeded(false);
                                    }}
                                >
                                    {({ processing, errors }) => (
                                        <div className="grid gap-1">
                                            <Button disabled={processing}>
                                                <Play aria-hidden="true" />
                                                {processing
                                                    ? 'Queueing…'
                                                    : currentExperiment.status ===
                                                        'failed'
                                                      ? 'Resume'
                                                      : 'Start'}
                                            </Button>
                                            <InputError
                                                message={errors.experiment}
                                            />
                                        </div>
                                    )}
                                </Form>
                            )}
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>
                                    Turn {currentExperiment.current_turn} of{' '}
                                    {currentExperiment.max_turns}
                                </span>
                                <span>{progress}%</span>
                            </div>
                            <div
                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-label="Conversation progress"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={progress}
                            >
                                <div
                                    className="h-full rounded-full bg-primary transition-[width] duration-500"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>
                        <div className="flex gap-4 text-xs text-muted-foreground">
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {currentMessages.length}
                                </strong>{' '}
                                messages
                            </span>
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {currentVocabulary.length}
                                </strong>{' '}
                                symbols
                            </span>
                        </div>
                    </div>
                </header>

                {pollingError && (
                    <div
                        className="flex shrink-0 items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
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
                        className="shrink-0 rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950"
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

                <div className="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section
                        className="flex min-h-[34rem] min-w-0 flex-col overflow-hidden rounded-2xl border bg-card shadow-sm lg:min-h-0"
                        aria-labelledby="conversation-title"
                    >
                        <div className="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-3.5 sm:px-5">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                    <MessageSquareText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div className="min-w-0">
                                    <h2
                                        id="conversation-title"
                                        className="truncate text-sm font-semibold"
                                    >
                                        Live agent conversation
                                    </h2>
                                    <p className="truncate text-xs text-muted-foreground">
                                        New messages are inserted automatically
                                        as each agent responds.
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="xl:hidden"
                                onClick={() => setVocabularyOpen(true)}
                            >
                                <BookOpenText aria-hidden="true" />
                                Vocabulary
                            </Button>
                        </div>

                        <div className="relative min-h-0 flex-1">
                            <div
                                ref={conversationRef}
                                onScroll={handleConversationScroll}
                                className="h-full overflow-y-auto px-4 py-5 sm:px-6"
                            >
                                {groupedMessages.length === 0 &&
                                composingRole === null ? (
                                    <div className="flex min-h-72 items-center justify-center">
                                        <div className="max-w-sm rounded-2xl border border-dashed p-8 text-center">
                                            <MessageSquareText
                                                className="mx-auto size-7 text-muted-foreground/50"
                                                aria-hidden="true"
                                            />
                                            <h3 className="mt-3 text-sm font-semibold">
                                                Waiting for the first message
                                            </h3>
                                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                The conversation will populate
                                                here without a page refresh.
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {composingRole !== null && (
                                            <div className="mb-3">
                                                <ComposingIndicator
                                                    role={composingRole}
                                                />
                                            </div>
                                        )}

                                        {groupedMessages.length > 0 && (
                                            <ol className="flex flex-col gap-4">
                                                {groupedMessages.map(
                                                    ([turn, turnMessages]) => (
                                                        <li
                                                            key={turn}
                                                            aria-label={`Turn ${turn}`}
                                                        >
                                                            <ol className="flex flex-col gap-3">
                                                                {turnMessages.map(
                                                                    (
                                                                        message,
                                                                    ) => {
                                                                        const isSender =
                                                                            message.role ===
                                                                            'sender';

                                                                        return (
                                                                            <li
                                                                                key={
                                                                                    message.id
                                                                                }
                                                                                className={`flex ${
                                                                                    isSender
                                                                                        ? 'justify-end'
                                                                                        : 'justify-start'
                                                                                }`}
                                                                            >
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() =>
                                                                                        setSelectedMessage(
                                                                                            message,
                                                                                        )
                                                                                    }
                                                                                    className={`group w-full max-w-4xl rounded-2xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none ${
                                                                                        isSender
                                                                                            ? 'rounded-tr-md bg-background'
                                                                                            : 'rounded-tl-md bg-muted/45'
                                                                                    }`}
                                                                                    aria-label={`Inspect turn ${message.turn} ${message.role} message`}
                                                                                >
                                                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                                                        <div className="flex items-center gap-2">
                                                                                            <span
                                                                                                className={`flex size-7 items-center justify-center rounded-full text-xs font-bold ${
                                                                                                    isSender
                                                                                                        ? 'bg-primary text-primary-foreground'
                                                                                                        : 'bg-secondary text-secondary-foreground'
                                                                                                }`}
                                                                                            >
                                                                                                {isSender
                                                                                                    ? 'S'
                                                                                                    : 'R'}
                                                                                            </span>
                                                                                            <span className="text-sm font-semibold capitalize">
                                                                                                {
                                                                                                    message.role
                                                                                                }
                                                                                            </span>
                                                                                        </div>
                                                                                        <span className="text-xs text-muted-foreground">
                                                                                            {formatTime(
                                                                                                message.created_at,
                                                                                            )}
                                                                                        </span>
                                                                                    </div>

                                                                                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                                                                                        <div className="rounded-xl border bg-card/80 p-3">
                                                                                            <p className="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                                                                                Symbolic
                                                                                            </p>
                                                                                            <p className="mt-1.5 font-mono text-sm leading-6 break-words whitespace-pre-wrap">
                                                                                                {
                                                                                                    message.symbolic_content
                                                                                                }
                                                                                            </p>
                                                                                        </div>
                                                                                        <div className="rounded-xl border bg-card/80 p-3">
                                                                                            <p className="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                                                                                Decoded
                                                                                            </p>
                                                                                            <p className="mt-1.5 text-sm leading-6 break-words whitespace-pre-wrap">
                                                                                                {
                                                                                                    message.decoded_content
                                                                                                }
                                                                                            </p>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div className="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                                                                                        <span>
                                                                                            {
                                                                                                message
                                                                                                    .vocabulary
                                                                                                    .length
                                                                                            }{' '}
                                                                                            {message
                                                                                                .vocabulary
                                                                                                .length ===
                                                                                            1
                                                                                                ? 'symbol'
                                                                                                : 'symbols'}
                                                                                        </span>
                                                                                        <span className="font-medium text-foreground opacity-60 transition group-hover:opacity-100">
                                                                                            View
                                                                                            details
                                                                                            →
                                                                                        </span>
                                                                                    </div>
                                                                                </button>
                                                                            </li>
                                                                        );
                                                                    },
                                                                )}
                                                            </ol>
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        )}

                                    </>
                                )}
                            </div>

                            {showJumpToLatest && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={jumpToLatest}
                                    className="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-background shadow-lg"
                                >
                                    <ArrowUp aria-hidden="true" />
                                    Jump to latest
                                </Button>
                            )}
                        </div>
                    </section>

                    <aside
                        className="hidden min-h-0 overflow-y-auto rounded-2xl border bg-card p-4 shadow-sm xl:block"
                        aria-labelledby="vocabulary-title"
                    >
                        <div className="sticky top-0 z-10 mb-4 border-b bg-card pb-3">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2
                                        id="vocabulary-title"
                                        className="text-sm font-semibold"
                                    >
                                        Active vocabulary
                                    </h2>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        Latest definition for each symbol
                                    </p>
                                </div>
                                <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">
                                    {currentVocabulary.length}
                                </span>
                            </div>
                        </div>
                        <VocabularyList vocabulary={currentVocabulary} />
                    </aside>
                </div>

                <PromptConfigurationDialog
                    experiment={currentExperiment}
                    open={promptConfigurationOpen}
                    onOpenChange={setPromptConfigurationOpen}
                />
            </div>

            <MessageDetailDialog
                message={selectedMessage}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedMessage(null);
                    }
                }}
            />

            <Dialog open={vocabularyOpen} onOpenChange={setVocabularyOpen}>
                <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Active vocabulary</DialogTitle>
                        <DialogDescription>
                            The latest immutable definition for each symbol.
                        </DialogDescription>
                    </DialogHeader>
                    <VocabularyList vocabulary={currentVocabulary} />
                </DialogContent>
            </Dialog>

            <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
                <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Vocabulary history</DialogTitle>
                        <DialogDescription>
                            Append-only creation and revision events from this
                            experiment.
                        </DialogDescription>
                    </DialogHeader>
                    {currentEvents.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            History will appear as definitions evolve.
                        </p>
                    ) : (
                        <ol className="flex flex-col gap-4 border-l pl-5">
                            {currentEvents.map((event) => (
                                <li key={event.id} className="relative">
                                    <span
                                        className="absolute top-1.5 -left-[1.48rem] size-2 rounded-full bg-primary"
                                        aria-hidden="true"
                                    />
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span className="font-semibold text-foreground capitalize">
                                            {event.type}
                                        </span>
                                        <span className="font-mono">
                                            {`[[${event.symbol}]]`}
                                        </span>
                                        <span>v{event.version}</span>
                                    </div>
                                    <p className="mt-1 text-sm leading-6">
                                        {event.meaning}
                                    </p>
                                    {event.description && (
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            {event.description}
                                        </p>
                                    )}
                                    <p className="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock3
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        {event.proposer_role}, turn {event.turn}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </DialogContent>
            </Dialog>
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
