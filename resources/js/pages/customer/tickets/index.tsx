import AppLogoIcon from '@/components/app-logo-icon';
import { PriorityBadge, StatusBadge } from '@/components/desk-badges';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { relativeTime } from '@/lib/desk';
import type { TicketPriority, TicketStatus } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

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

interface CustomerWorkspace {
    id: number;
    name: string;
}

export default function CustomerTicketsIndex({ ticketGroups, workspaces }: { ticketGroups: TicketGroup[]; workspaces: CustomerWorkspace[] }) {
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ workspace_id: workspaces[0]?.id ?? '', subject: '', body: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('customer.tickets.store'), {
            onSuccess: () => {
                form.reset('subject', 'body');
                setShowForm(false);
            },
        });
    };

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
                        {workspaces.length > 0 && (
                            <Button size="sm" onClick={() => setShowForm((open) => !open)}>
                                New ticket
                            </Button>
                        )}
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

                {showForm && (
                    <section className="bg-card space-y-4 rounded-sm border p-4 sm:p-6">
                        <div>
                            <h2 className="font-display text-lg font-semibold tracking-tight">Contact support</h2>
                            <p className="text-muted-foreground mt-1 text-sm">Tell us what you need help with.</p>
                        </div>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="workspace">Workspace</Label>
                                <select
                                    id="workspace"
                                    value={form.data.workspace_id}
                                    onChange={(event) => form.setData('workspace_id', Number(event.target.value))}
                                    className="border-input bg-background ring-offset-background focus:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-offset-2 focus:outline-none"
                                >
                                    {workspaces.map((workspace) => (
                                        <option key={workspace.id} value={workspace.id}>
                                            {workspace.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.workspace_id} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="subject">Subject</Label>
                                <Input id="subject" value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} required />
                                <InputError message={form.errors.subject} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="body">Message</Label>
                                <Textarea id="body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} required />
                                <InputError message={form.errors.body} />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Send ticket
                            </Button>
                        </form>
                    </section>
                )}

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
