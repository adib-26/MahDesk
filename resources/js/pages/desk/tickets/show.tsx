import { ChannelBadge, PriorityBadge, StatusBadge, TagChip } from '@/components/desk-badges';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { CHANNEL_LABELS, deskRoute, formatMinutes, PRIORITY_LABELS, relativeTime, STATUS_LABELS } from '@/lib/desk';
import { cn } from '@/lib/utils';
import type { SharedData, Tag, Ticket, TicketPriority, TicketStatus } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    ticket: Ticket;
    agents: { id: number; name: string }[];
    workspaceTags: Tag[];
    contactTicketCount: number;
}

export default function TicketShow({ ticket, agents, workspaceTags, contactTicketCount }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;

    const update = (payload: Record<string, unknown>) => {
        router.patch(deskRoute('desk.tickets.update', workspace, { ticket: ticket.id }), payload, { preserveScroll: true });
    };

    const reply = useForm({ kind: 'reply' as 'reply' | 'note', body: '', status: '' });
    const submitReply: FormEventHandler = (event) => {
        event.preventDefault();
        reply.post(deskRoute('desk.tickets.messages.store', workspace, { ticket: ticket.id }), {
            preserveScroll: true,
            onSuccess: () => reply.reset('body'),
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Tickets', href: deskRoute('desk.tickets.index', workspace) },
                { title: `#${ticket.number}`, href: deskRoute('desk.tickets.show', workspace, { ticket: ticket.id }) },
            ]}
        >
            <Head title={`#${ticket.number} ${ticket.subject}`} />
            <div className="grid gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_320px] md:p-6">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={ticket.status} />
                            <PriorityBadge priority={ticket.priority} />
                            <ChannelBadge channel={ticket.channel} />
                            {(ticket.first_response_breached || ticket.resolution_breached) && (
                                <span className="text-sm font-medium text-red-600">SLA breached</span>
                            )}
                        </div>
                        <h1 className="text-2xl font-semibold">
                            #{ticket.number} {ticket.subject}
                        </h1>
                    </div>

                    <div className="flex flex-col gap-3">
                        {ticket.messages?.map((message) => (
                            <div
                                key={message.id}
                                className={cn(
                                    'rounded-xl border p-4',
                                    message.kind === 'note' && 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30',
                                    message.kind === 'event' && 'text-muted-foreground border-dashed text-sm',
                                )}
                            >
                                <div className="mb-2 flex items-center justify-between gap-3 text-xs">
                                    <span className="font-medium">
                                        {message.kind === 'event'
                                            ? 'System'
                                            : message.is_from_contact
                                              ? ticket.contact?.name
                                              : (message.author?.name ?? 'Agent')}
                                        {message.kind === 'note' && ' · Internal note'}
                                    </span>
                                    <span>{relativeTime(message.created_at)}</span>
                                </div>
                                <p className="whitespace-pre-wrap text-sm">{message.body}</p>
                            </div>
                        ))}
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Reply</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitReply} className="flex flex-col gap-3">
                                <div className="flex gap-2">
                                    <Button type="button" size="sm" variant={reply.data.kind === 'reply' ? 'default' : 'outline'} onClick={() => reply.setData('kind', 'reply')}>
                                        Public reply
                                    </Button>
                                    <Button type="button" size="sm" variant={reply.data.kind === 'note' ? 'default' : 'outline'} onClick={() => reply.setData('kind', 'note')}>
                                        Internal note
                                    </Button>
                                </div>
                                <Textarea
                                    value={reply.data.body}
                                    onChange={(event) => reply.setData('body', event.target.value)}
                                    placeholder={reply.data.kind === 'note' ? 'Visible only to agents…' : 'Reply to the customer…'}
                                    required
                                />
                                <InputError message={reply.errors.body} />
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div className="grid flex-1 gap-2">
                                        <Label>Also set status</Label>
                                        <Select value={reply.data.status || 'keep'} onValueChange={(value) => reply.setData('status', value === 'keep' ? '' : value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="keep">Keep current status</SelectItem>
                                                    {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button type="submit" disabled={reply.processing}>
                                        {reply.data.kind === 'note' ? 'Add note' : 'Send reply'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <aside className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Properties</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <Field label="Status">
                                <Select value={ticket.status} onValueChange={(value) => update({ status: value as TicketStatus })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Priority">
                                <Select value={ticket.priority} onValueChange={(value) => update({ priority: value as TicketPriority })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {Object.entries(PRIORITY_LABELS).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Assignee">
                                <Select value={ticket.assignee_id ? String(ticket.assignee_id) : 'none'} onValueChange={(value) => update({ assignee_id: value === 'none' ? null : Number(value) })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="none">Unassigned</SelectItem>
                                            {agents.map((agent) => (
                                                <SelectItem key={agent.id} value={String(agent.id)}>
                                                    {agent.name}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <p className="text-muted-foreground text-xs">Channel: {CHANNEL_LABELS[ticket.channel]}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Customer</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1 text-sm">
                            <Link href={deskRoute('desk.contacts.show', workspace, { contact: ticket.contact_id })} className="font-medium hover:underline">
                                {ticket.contact?.name}
                            </Link>
                            <p className="text-muted-foreground">{ticket.contact?.email}</p>
                            {ticket.contact?.company && <p>{ticket.contact.company}</p>}
                            <p className="text-muted-foreground text-xs">{contactTicketCount} tickets on file</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SLA</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 text-sm">
                            <p>{ticket.sla_policy?.name ?? 'No policy matched'}</p>
                            <p className={ticket.first_response_breached ? 'text-red-600' : 'text-muted-foreground'}>
                                First response {relativeTime(ticket.first_response_due_at)}
                                {ticket.first_responded_at && ` · replied ${relativeTime(ticket.first_responded_at)}`}
                            </p>
                            <p className={ticket.resolution_breached ? 'text-red-600' : 'text-muted-foreground'}>
                                Resolution {relativeTime(ticket.resolution_due_at)}
                            </p>
                            {ticket.sla_policy && (
                                <p className="text-muted-foreground text-xs">
                                    Targets {formatMinutes(ticket.sla_policy.first_response_minutes)} / {formatMinutes(ticket.sla_policy.resolution_minutes)}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Tags</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="flex flex-wrap gap-1">
                                {ticket.tags?.length ? ticket.tags.map((tag) => <TagChip key={tag.id} tag={tag} />) : <p className="text-muted-foreground text-sm">No tags</p>}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {workspaceTags.map((tag) => {
                                    const selected = ticket.tags?.some((item) => item.id === tag.id);
                                    return (
                                        <Button
                                            key={tag.id}
                                            size="sm"
                                            variant={selected ? 'default' : 'outline'}
                                            onClick={() => {
                                                const next = selected
                                                    ? (ticket.tags ?? []).filter((item) => item.id !== tag.id).map((item) => item.id)
                                                    : [...(ticket.tags ?? []).map((item) => item.id), tag.id];
                                                update({ tag_ids: next });
                                            }}
                                        >
                                            {tag.name}
                                        </Button>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
