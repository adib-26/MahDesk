import { PriorityBadge, StatusBadge } from '@/components/desk-badges';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { relativeTime } from '@/lib/desk';
import type { TicketPriority, TicketStatus } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface CustomerMessage {
    id: number;
    body: string;
    is_from_contact: boolean;
    created_at: string | null;
}

interface CustomerTicket {
    id: number;
    number: number;
    subject: string;
    status: TicketStatus;
    priority: TicketPriority;
    created_at: string | null;
    updated_at: string | null;
    workspace: { name: string };
    messages: CustomerMessage[];
}

export default function CustomerTicketShow({ ticket }: { ticket: CustomerTicket }) {
    const reply = useForm({ body: '' });

    const submitReply: FormEventHandler = (event) => {
        event.preventDefault();

        reply.post(route('customer.tickets.messages.store', { ticket: ticket.id }), {
            preserveScroll: true,
            onSuccess: () => reply.reset(),
        });
    };

    return (
        <div className="bg-background min-h-screen">
            <Head title={`#${ticket.number} ${ticket.subject}`} />

            <header className="border-b">
                <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-5 sm:px-6">
                    <Link href={route('customer.tickets.index')} className="text-lg font-semibold">
                        OmniDesk Support
                    </Link>
                    <span className="text-muted-foreground text-sm">{ticket.workspace.name}</span>
                </div>
            </header>

            <main className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
                <Link href={route('customer.tickets.index')} className="text-muted-foreground text-sm underline">
                    Back to your tickets
                </Link>

                <section className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={ticket.status} />
                        <PriorityBadge priority={ticket.priority} />
                    </div>
                    <h1 className="text-2xl font-semibold">
                        #{ticket.number} {ticket.subject}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Opened {relativeTime(ticket.created_at)} · Updated {relativeTime(ticket.updated_at)}
                    </p>
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">Conversation</h2>
                    {ticket.messages.length === 0 ? (
                        <p className="text-muted-foreground rounded-xl border p-4 text-sm">There are no replies yet.</p>
                    ) : (
                        ticket.messages.map((message) => (
                            <article
                                key={message.id}
                                className={
                                    message.is_from_contact
                                        ? 'bg-muted/40 ml-auto max-w-[90%] rounded-xl border p-4'
                                        : 'mr-auto max-w-[90%] rounded-xl border p-4'
                                }
                            >
                                <div className="text-muted-foreground mb-2 flex items-center justify-between gap-4 text-xs">
                                    <span className="font-medium">{message.is_from_contact ? 'You' : 'Support'}</span>
                                    <span>{relativeTime(message.created_at)}</span>
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{message.body}</p>
                            </article>
                        ))
                    )}
                </section>

                <section className="space-y-4 rounded-xl border p-4 sm:p-6">
                    <div>
                        <h2 className="font-semibold">Reply to support</h2>
                        <p className="text-muted-foreground mt-1 text-sm">Your message will be shared with the support team.</p>
                    </div>

                    <form onSubmit={submitReply} className="space-y-3">
                        <Textarea
                            value={reply.data.body}
                            onChange={(event) => reply.setData('body', event.target.value)}
                            placeholder="Write your reply…"
                            required
                        />
                        <InputError message={reply.errors.body} />
                        <Button type="submit" disabled={reply.processing}>
                            Send reply
                        </Button>
                    </form>
                </section>
            </main>
        </div>
    );
}
