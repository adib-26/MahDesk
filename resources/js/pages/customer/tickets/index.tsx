import AppLogoIcon from '@/components/app-logo-icon';
import { PriorityBadge, StatusBadge } from '@/components/desk-badges';
import { Button } from '@/components/ui/button';
import { relativeTime } from '@/lib/desk';
import type { TicketPriority, TicketStatus } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface CustomerTicket {
    id: number;
    number: number;
    subject: string;
    status: TicketStatus;
    priority: TicketPriority;
    updated_at: string | null;
}

interface TicketGroup {
    id: number;
    name: string;
    tickets: CustomerTicket[];
}

export default function CustomerTicketsIndex({ ticketGroups }: { ticketGroups: TicketGroup[] }) {
    return (
        <div className="bg-background min-h-screen">
            <Head title="My tickets" />

            <header className="border-b">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-5 sm:px-6">
                    <Link href={route('customer.tickets.index')} className="flex items-center gap-2.5">
                        <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-sm">
                            <AppLogoIcon className="size-4.5" />
                        </span>
                        <span className="font-display text-lg font-semibold tracking-tight">OmniDesk Support</span>
                    </Link>
                    <div className="flex items-center gap-3">
                        <span className="text-muted-foreground text-sm">My tickets</span>
                        <Button variant="ghost" size="sm" onClick={() => router.post(route('logout'))}>
                            Log out
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6">
                <div>
                    <p className="stamp-label text-muted-foreground">Your ledger</p>
                    <h1 className="font-display mt-1 text-3xl font-semibold tracking-tight">Your support tickets</h1>
                    <p className="text-muted-foreground mt-1 text-sm">Follow conversations and reply to your support requests.</p>
                </div>

                {ticketGroups.length === 0 ? (
                    <div className="text-muted-foreground rounded-sm border border-dashed p-8 text-center text-sm">
                        You do not have any support tickets yet.
                    </div>
                ) : (
                    ticketGroups.map((group) => (
                        <section key={group.id} className="space-y-3">
                            <h2 className="font-display text-lg font-semibold tracking-tight">{group.name}</h2>
                            <div className="bg-card overflow-hidden rounded-sm border">
                                {group.tickets.map((ticket) => (
                                    <Link
                                        key={ticket.id}
                                        href={route('customer.tickets.show', { ticket: ticket.id })}
                                        className="hover:bg-muted/40 flex flex-col gap-3 border-b p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                <span className="text-stamp font-mono text-xs">#{ticket.number}</span> {ticket.subject}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-sm">Updated {relativeTime(ticket.updated_at)}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <StatusBadge status={ticket.status} />
                                            <PriorityBadge priority={ticket.priority} />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    ))
                )}

                <Button variant="outline" asChild>
                    <Link href={route('customer.tickets.index')}>Refresh tickets</Link>
                </Button>
            </main>
        </div>
    );
}
