import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

const features = [
    ['Multi-tenant workspaces', 'Each company gets an isolated workspace for tickets, customers, and agents.'],
    ['Ticket workflow', 'Status, priority, assignment, public replies, and internal notes.'],
    ['SLA + automation', 'Priority-based due dates and rules that tag, assign, or escalate tickets.'],
    ['Knowledge + analytics', 'Publish a help center and watch volume, resolution time, and SLA health.'],
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Desk" />
            <div className="bg-background min-h-screen">
                <header className="mx-auto flex max-w-5xl items-center justify-between p-6">
                    <div className="text-lg font-semibold">Desk</div>
                    <nav className="flex items-center gap-3">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>Open dashboard</Link>
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
                </header>
                <main className="mx-auto flex max-w-5xl flex-col gap-12 px-6 pb-20">
                    <section className="flex max-w-2xl flex-col gap-4 pt-8">
                        <p className="text-muted-foreground text-sm tracking-wide uppercase">Customer support, multi-tenant</p>
                        <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">A Zendesk-style desk for every company on one platform.</h1>
                        <p className="text-muted-foreground text-lg">
                            Desk is a Laravel + React support workspace: tickets, customers, agents, SLAs, automations, knowledge base, and analytics —
                            scoped per company.
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <Button asChild>
                                <Link href={route('register')}>Start a workspace</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('login')}>Use the demo</Link>
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Demo login: <span className="text-foreground font-medium">owner@desk.test</span> / <span className="text-foreground font-medium">password</span>
                        </p>
                    </section>
                    <section className="grid gap-4 md:grid-cols-2">
                        {features.map(([title, body]) => (
                            <div key={title} className="rounded-xl border p-5">
                                <h2 className="font-semibold">{title}</h2>
                                <p className="text-muted-foreground mt-1 text-sm">{body}</p>
                        </div>
                        ))}
                    </section>
                    </main>
            </div>
        </>
    );
}
