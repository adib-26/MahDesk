import { ChannelBadge, PriorityBadge, StatusBadge, TagChip } from '@/components/desk-badges';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { CHANNEL_LABELS, deskRoute, PRIORITY_LABELS, relativeTime, STATUS_LABELS } from '@/lib/desk';
import type { Contact, Paginated, SharedData, Tag, Ticket, TicketChannel, TicketPriority, TicketStatus } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    tickets: Paginated<Ticket>;
    filters: { status: string; priority?: string; assignee?: string; tag?: string; q?: string };
    statusCounts: Record<string, number>;
    agents: { id: number; name: string }[];
    tags: Tag[];
    contacts: Contact[];
}

const statuses: Array<TicketStatus | 'all'> = ['all', 'open', 'pending', 'on_hold', 'resolved', 'closed'];

export default function TicketsIndex({ tickets, filters, statusCounts, agents, tags, contacts }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const [open, setOpen] = useState(false);

    const apply = (next: Partial<Props['filters']>) => {
        router.get(deskRoute('desk.tickets.index', workspace), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Tickets', href: deskRoute('desk.tickets.index', workspace) }]}>
            <Head title="Tickets" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Tickets</h1>
                        <p className="text-muted-foreground text-sm">Triage, assign, and resolve customer requests.</p>
                    </div>
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus /> New ticket
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>Create ticket</DialogTitle>
                                <DialogDescription>Log a request from an existing customer or create one inline.</DialogDescription>
                            </DialogHeader>
                            <CreateTicketForm contacts={contacts} agents={agents} onCreated={() => setOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap gap-2">
                    {statuses.map((status) => {
                        const count = status === 'all' ? Object.values(statusCounts).reduce((sum, value) => sum + Number(value), 0) : Number(statusCounts[status] ?? 0);
                        return (
                            <Button key={status} size="sm" variant={filters.status === status ? 'default' : 'outline'} onClick={() => apply({ status })}>
                                {status === 'all' ? 'All' : STATUS_LABELS[status]} ({count})
                            </Button>
                        );
                    })}
                </div>

                <div className="grid gap-3 md:grid-cols-4">
                    <Input placeholder="Search subject, #, or customer" defaultValue={filters.q} onKeyDown={(event) => event.key === 'Enter' && apply({ q: event.currentTarget.value })} />
                    <Select value={filters.priority ?? 'any'} onValueChange={(value) => apply({ priority: value === 'any' ? undefined : value })}>
                        <SelectTrigger>
                            <SelectValue placeholder="Priority" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="any">Any priority</SelectItem>
                                {Object.entries(PRIORITY_LABELS).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select value={filters.assignee ?? 'any'} onValueChange={(value) => apply({ assignee: value === 'any' ? undefined : value })}>
                        <SelectTrigger>
                            <SelectValue placeholder="Assignee" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="any">Anyone</SelectItem>
                                <SelectItem value="me">Assigned to me</SelectItem>
                                <SelectItem value="unassigned">Unassigned</SelectItem>
                                {agents.map((agent) => (
                                    <SelectItem key={agent.id} value={String(agent.id)}>
                                        {agent.name}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select value={filters.tag ?? 'any'} onValueChange={(value) => apply({ tag: value === 'any' ? undefined : value })}>
                        <SelectTrigger>
                            <SelectValue placeholder="Tag" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="any">Any tag</SelectItem>
                                {tags.map((tag) => (
                                    <SelectItem key={tag.id} value={String(tag.id)}>
                                        {tag.name}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Ticket</th>
                                <th className="px-4 py-3 font-medium">Customer</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Priority</th>
                                <th className="px-4 py-3 font-medium">Assignee</th>
                                <th className="px-4 py-3 font-medium">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tickets.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        No tickets match these filters.
                                    </td>
                                </tr>
                            )}
                            {tickets.data.map((ticket) => (
                                <tr key={ticket.id} className="hover:bg-muted/40 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={deskRoute('desk.tickets.show', workspace, { ticket: ticket.id })} className="font-medium hover:underline">
                                            #{ticket.number} {ticket.subject}
                                        </Link>
                                        <div className="mt-1 flex flex-wrap items-center gap-1">
                                            <ChannelBadge channel={ticket.channel} />
                                            {ticket.tags?.map((tag) => (
                                                <TagChip key={tag.id} tag={tag} />
                                            ))}
                                            {(ticket.first_response_breached || ticket.resolution_breached) && (
                                                <span className="text-xs text-red-600">SLA breached</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{ticket.contact?.name}</p>
                                        <p className="text-muted-foreground text-xs">{ticket.contact?.email}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={ticket.status} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <PriorityBadge priority={ticket.priority} />
                                    </td>
                                    <td className="px-4 py-3">{ticket.assignee?.name ?? 'Unassigned'}</td>
                                    <td className="text-muted-foreground px-4 py-3">{relativeTime(ticket.updated_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination collection={tickets} />
            </div>
        </AppLayout>
    );
}

function CreateTicketForm({
    contacts,
    agents,
    onCreated,
}: {
    contacts: Contact[];
    agents: { id: number; name: string }[];
    onCreated: () => void;
}) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const form = useForm({
        subject: '',
        body: '',
        priority: 'normal' as TicketPriority,
        channel: 'web' as TicketChannel,
        assignee_id: '',
        contact_id: '',
        contact_name: '',
        contact_email: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            assignee_id: data.assignee_id || null,
            contact_id: data.contact_id || null,
        }));
        form.post(deskRoute('desk.tickets.store', currentWorkspace!), {
            onSuccess: () => {
                form.reset();
                onCreated();
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="grid gap-2">
                <Label htmlFor="subject">Subject</Label>
                <Input id="subject" value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} required />
                <InputError message={form.errors.subject} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="body">Message</Label>
                <Textarea id="body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} required />
                <InputError message={form.errors.body} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label>Priority</Label>
                    <Select value={form.data.priority} onValueChange={(value) => form.setData('priority', value as TicketPriority)}>
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
                </div>
                <div className="grid gap-2">
                    <Label>Channel</Label>
                    <Select value={form.data.channel} onValueChange={(value) => form.setData('channel', value as TicketChannel)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {Object.entries(CHANNEL_LABELS).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div className="grid gap-2">
                <Label>Existing customer</Label>
                <Select value={form.data.contact_id || 'new'} onValueChange={(value) => form.setData('contact_id', value === 'new' ? '' : value)}>
                    <SelectTrigger>
                        <SelectValue placeholder="Create a new customer" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="new">Create a new customer</SelectItem>
                            {contacts.map((contact) => (
                                <SelectItem key={contact.id} value={String(contact.id)}>
                                    {contact.name} · {contact.email}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
            {!form.data.contact_id && (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="contact_name">Customer name</Label>
                        <Input id="contact_name" value={form.data.contact_name} onChange={(event) => form.setData('contact_name', event.target.value)} />
                        <InputError message={form.errors.contact_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contact_email">Customer email</Label>
                        <Input id="contact_email" type="email" value={form.data.contact_email} onChange={(event) => form.setData('contact_email', event.target.value)} />
                        <InputError message={form.errors.contact_email} />
                    </div>
                </div>
            )}
            <div className="grid gap-2">
                <Label>Assignee</Label>
                <Select value={form.data.assignee_id || 'none'} onValueChange={(value) => form.setData('assignee_id', value === 'none' ? '' : value)}>
                    <SelectTrigger>
                        <SelectValue placeholder="Unassigned" />
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
            </div>
            <Button type="submit" disabled={form.processing}>
                Create ticket
            </Button>
        </form>
    );
}
