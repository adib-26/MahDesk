import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

const features = [
    ['Multi-tenant workspaces', 'Each company keeps its own ledger: tickets, customers, and agents, ruled off from every other.'],
    ['Ticket workflow', 'Status, priority, assignment, public replies, and internal notes — every line item accounted for.'],
    ['SLA + automation', 'Priority-based due dates and rules that tag, assign, or escalate before a balance goes overdue.'],
    ['Knowledge + analytics', 'Publish a help center and read volume, resolution time, and SLA health at a glance.'],
] as const;

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="MahbubDesk" />
            <div className="paper-grain bg-background min-h-screen">
                <header className="border-b">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                        <div className="flex items-center gap-3">
                            <div className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-sm">
                                <AppLogoIcon className="size-6" />
                            </div>
                            <span className="font-display text-xl font-semibold tracking-tight">MahbubDesk</span>
                        </div>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild>
                                    <Link href={route('dashboard')}>Open the ledger</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="ghost" asChild>
                                        <Link href={route('login')}>Log in</Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={route('register')}>Create workspace</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto flex max-w-5xl flex-col px-6 pb-24">
                    <section className="flex max-w-3xl flex-col gap-6 pt-16 md:pt-24">
                        <p className="stamp-label text-muted-foreground flex items-center gap-3">
                            <span className="bg-foreground inline-block h-px w-8" />
                            № 001 · Customer support, kept in one ledger
                        </p>
                        <h1 className="font-display text-5xl leading-[1.05] font-medium tracking-tight text-balance md:text-6xl">
                            Every customer request, <em className="text-stamp font-medium italic">entered in one ledger.</em>
                        </h1>
                        <p className="text-muted-foreground max-w-xl text-lg leading-relaxed">
                            MahbubDesk is a multi-tenant support desk: tickets, customers, agents, SLAs, automations, a knowledge base, and
                            analytics — ruled per company, kept in ink.
                        </p>
                        <div className="flex flex-wrap items-center gap-3 pt-1">
                            <Button size="lg" asChild>
                                <Link href={route('register')}>Start a workspace</Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link href={route('login')}>Open the demo</Link>
                            </Button>
                        </div>

                        <div className="relative mt-4 max-w-md">
                            <div className="border-foreground/40 relative rounded-sm border border-dashed px-5 py-4">
                                <span className="bg-background border-foreground/40 absolute top-1/2 -left-[9px] size-4 -translate-y-1/2 rounded-full border border-dashed" />
                                <span className="bg-background border-foreground/40 absolute top-1/2 -right-[9px] size-4 -translate-y-1/2 rounded-full border border-dashed" />
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="stamp-label text-stamp">Demo access</p>
                                        <p className="mt-1.5 font-mono text-sm">
                                            owner@desk.test <span className="text-muted-foreground">/</span> password
                                        </p>
                                    </div>
                                    <AppLogoIcon className="text-muted-foreground/50 size-10 shrink-0 -rotate-6" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="mt-20 md:mt-28">
                        <p className="stamp-label text-muted-foreground mb-6">What the ledger keeps</p>
                        <div className="rule-double" />
                        <dl className="divide-border divide-y">
                            {features.map(([title, body], index) => (
                                <div key={title} className="grid gap-2 py-6 md:grid-cols-[4rem_1fr_2fr] md:items-baseline md:gap-6">
                                    <dt className="stamp-label text-stamp">{String(index + 1).padStart(2, '0')}</dt>
                                    <dt className="font-display text-xl font-semibold tracking-tight">{title}</dt>
                                    <dd className="text-muted-foreground text-sm leading-relaxed">{body}</dd>
                                </div>
                            ))}
                        </dl>
                        <div className="border-b" />
                    </section>
                </main>

                <footer className="border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-5xl items-center justify-between px-6 py-6 font-mono text-xs">
                        <span>MahbubDesk — support, on the record.</span>
                        <span>Laravel · Inertia · React</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
