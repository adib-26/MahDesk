import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { deskRoute, relativeTime } from '@/lib/desk';
import type { InboundEmail, Paginated, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface Props {
    emails: Paginated<InboundEmail>;
    inboundAddress: string;
}

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    processing: 'Processing',
    created: 'Ticket created',
    replied: 'Added to ticket',
    ignored: 'Ignored',
    failed: 'Failed',
};

export default function EmailInbox({ emails, inboundAddress }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;

    return (
        <AppLayout breadcrumbs={[{ title: 'Email', href: deskRoute('desk.emails.index', workspace) }]}>
            <Head title="Email inbox" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-3xl font-semibold tracking-tight">Email inbox</h1>
                    <p className="text-muted-foreground text-sm">Messages received by this workspace through the configured email provider.</p>
                </div>

                {emails.data.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No emails have been received yet. Send a message to <code>{inboundAddress}</code>. New mail is checked every minute.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-col gap-4">
                        {emails.data.map((email) => (
                            <Card key={email.id}>
                                <CardHeader className="gap-1">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-lg">{email.subject}</CardTitle>
                                            <p className="text-muted-foreground text-sm">From {email.from}</p>
                                        </div>
                                        <span className="text-muted-foreground text-xs">
                                            {STATUS_LABELS[email.status] ?? email.status} · {relativeTime(email.created_at)}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    {email.body ? (
                                        <p className="whitespace-pre-wrap text-sm">{email.body}</p>
                                    ) : email.html ? (
                                        <p className="text-muted-foreground text-sm">This email contains HTML content. Open the linked ticket to view the processed message.</p>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">No message body.</p>
                                    )}
                                    {email.status_reason && <p className="text-muted-foreground text-xs">{email.status_reason}</p>}
                                    {email.ticket && (
                                        <Link className="text-sm font-medium underline" href={deskRoute('desk.tickets.show', workspace, { ticket: email.ticket.id })}>
                                            View ticket #{email.ticket.number}
                                        </Link>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
