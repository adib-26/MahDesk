import AppLogoIcon from '@/components/app-logo-icon';
import { AttachmentList, AttachmentPicker, attachmentError, type AttachmentItem, type AttachmentLimits } from '@/components/attachments';
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
    attachments: AttachmentItem[];
}

interface CustomerTicket {
    id: number;
    number: number;
    subject: string;
    status: TicketStatus;
    priority: TicketPriority;
    created_at: string | null;
    updated_at: string | null;
    satisfaction_rating: number | null;
    workspace: { name: string };
    messages: CustomerMessage[];
}

export default function CustomerTicketShow({ ticket, attachmentLimits }: { ticket: CustomerTicket; attachmentLimits: AttachmentLimits }) {
    const reply = useForm<{ body: string; attachments: File[] }>({ body: '', attachments: [] });
    const rating = useForm<{ rating: number }>({ rating: ticket.satisfaction_rating ?? 0 });
    const canRate = ticket.status === 'resolved' || ticket.status === 'closed';

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
                    <Link href={route('customer.tickets.index')} className="flex items-center gap-2.5">
                        <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-sm">
                            <AppLogoIcon className="size-4.5" />
                        </span>
                        <span className="font-display text-lg font-semibold tracking-tight">MahbubDesk</span>
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
                    <h1 className="font-display text-3xl font-semibold tracking-tight">
                        <span className="text-stamp font-mono text-xl font-medium">#{ticket.number}</span> {ticket.subject}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Opened {relativeTime(ticket.created_at)} · Updated {relativeTime(ticket.updated_at)}
                    </p>
                </section>

                <section className="space-y-3">
                    <h2 className="font-display text-lg font-semibold tracking-tight">Conversation</h2>
                    {ticket.messages.length === 0 ? (
                        <p className="text-muted-foreground rounded-sm border border-dashed p-4 text-sm">There are no replies yet.</p>
                    ) : (
                        ticket.messages.map((message) => (
                            <article
                                key={message.id}
                                className={
                                    message.is_from_contact
                                        ? 'bg-muted/40 ml-auto max-w-[90%] rounded-sm border p-4'
                                        : 'bg-card mr-auto max-w-[90%] rounded-sm border p-4'
                                }
                            >
                                <div className="text-muted-foreground mb-2 flex items-center justify-between gap-4 text-xs">
                                    <span className="font-medium">{message.is_from_contact ? 'You' : 'Support'}</span>
                                    <span>{relativeTime(message.created_at)}</span>
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{message.body}</p>
                                <AttachmentList attachments={message.attachments} />
                            </article>
                        ))
                    )}
                </section>

                {canRate && (
                    <section className="bg-card space-y-3 rounded-sm border p-4 sm:p-6">
                        <div>
                            <h2 className="font-display text-lg font-semibold tracking-tight">How did we do?</h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {ticket.satisfaction_rating
                                    ? `You rated this ${ticket.satisfaction_rating} out of 5. You can change it.`
                                    : 'Rate the help you received. Your score appears on the team’s satisfaction report.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Satisfaction rating">
                            {[1, 2, 3, 4, 5].map((score) => (
                                <Button
                                    key={score}
                                    type="button"
                                    size="sm"
                                    variant={ticket.satisfaction_rating === score ? 'default' : 'outline'}
                                    disabled={rating.processing}
                                    aria-pressed={ticket.satisfaction_rating === score}
                                    onClick={() => {
                                        rating.transform(() => ({ rating: score }));
                                        rating.post(route('customer.tickets.rate', { ticket: ticket.id }), { preserveScroll: true });
                                    }}
                                >
                                    {score}
                                </Button>
                            ))}
                        </div>
                        <InputError message={rating.errors.rating} />
                    </section>
                )}

                <section className="bg-card space-y-4 rounded-sm border p-4 sm:p-6">
                    <div>
                        <h2 className="font-display text-lg font-semibold tracking-tight">Reply to support</h2>
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
                        <AttachmentPicker
                            files={reply.data.attachments}
                            onChange={(files) => reply.setData('attachments', files)}
                            limits={attachmentLimits}
                            error={attachmentError(reply.errors as Record<string, string>)}
                        />
                        <Button type="submit" disabled={reply.processing}>
                            Send reply
                        </Button>
                    </form>
                </section>
            </main>
        </div>
    );
}
