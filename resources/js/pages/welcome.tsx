import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenText,
    FlaskConical,
    MessagesSquare,
} from 'lucide-react';
import { index } from '@/actions/App/Http/Controllers/ExperimentController';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="AI vocabulary experiments" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-6" />
                        </span>
                        <span className="font-semibold">Agent Lexicon Lab</span>
                    </div>
                    <nav
                        className="flex items-center gap-2"
                        aria-label="Account"
                    >
                        {auth.user ? (
                            <Button asChild>
                                <Link href={index()}>
                                    Open experiments
                                    <ArrowRight aria-hidden="true" />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto grid w-full max-w-6xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:py-28">
                        <div className="space-y-7">
                            <p className="inline-flex items-center gap-2 rounded-full border bg-muted/50 px-3 py-1.5 text-sm font-medium">
                                <FlaskConical
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Bounded, observable agent conversations
                            </p>
                            <div className="space-y-4">
                                <h1 className="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                                    Watch AI agents invent a language together.
                                </h1>
                                <p className="max-w-2xl text-lg leading-8 text-muted-foreground">
                                    A Laravel and React laboratory for observing
                                    how AI agents create, negotiate, and evolve
                                    a shared symbolic vocabulary.
                                </p>
                            </div>
                            {!auth.user && (
                                <div className="flex flex-wrap gap-3">
                                    <Button asChild size="lg">
                                        <Link href={register()}>
                                            Start a lab account
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                    <Button asChild size="lg" variant="outline">
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                </div>
                            )}
                        </div>

                        <div className="rounded-2xl border bg-card p-5 shadow-xl shadow-primary/5 sm:p-6">
                            <div className="mb-5 flex items-center justify-between">
                                <div>
                                    <p className="font-semibold">
                                        Example exchange
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Symbolic and decoded views
                                    </p>
                                </div>
                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                    Completed
                                </span>
                            </div>
                            <div className="flex flex-col gap-3">
                                <div className="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-900 dark:bg-violet-950/40">
                                    <p className="text-xs font-semibold text-violet-700 dark:text-violet-300">
                                        Sender · Turn 2
                                    </p>
                                    <p className="mt-2 font-mono text-sm">
                                        Follow [[north-signal]] after dusk.
                                    </p>
                                </div>
                                <div className="rounded-xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900 dark:bg-cyan-950/40">
                                    <p className="text-xs font-semibold text-cyan-700 dark:text-cyan-300">
                                        Receiver · Decoded
                                    </p>
                                    <p className="mt-2 text-sm">
                                        Follow the cool horizon marker after
                                        dusk.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-y bg-muted/30">
                        <div className="mx-auto grid w-full max-w-6xl gap-4 px-5 py-12 sm:px-8 md:grid-cols-3">
                            {[
                                {
                                    icon: MessagesSquare,
                                    title: 'Trace every turn',
                                    text: 'Compare sender and receiver messages in chronological order.',
                                },
                                {
                                    icon: BookOpenText,
                                    title: 'Inspect meanings',
                                    text: 'See current definitions and every immutable revision.',
                                },
                                {
                                    icon: FlaskConical,
                                    title: 'Control execution',
                                    text: 'Use bounded turns, queued work, and disabled-by-default paid scheduling.',
                                },
                            ].map((feature) => (
                                <article
                                    key={feature.title}
                                    className="rounded-xl border bg-card p-5"
                                >
                                    <feature.icon
                                        className="mb-4 size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <h2 className="font-semibold">
                                        {feature.title}
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {feature.text}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
