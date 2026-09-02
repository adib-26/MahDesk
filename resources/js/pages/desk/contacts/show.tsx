import { PriorityBadge, StatusBadge, TagChip } from '@/components/desk-badges';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { deskRoute, relativeTime } from '@/lib/desk';
import type { Contact, SharedData, Ticket } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    contact: Contact;
    tickets: Ticket[];
}

export default function ContactShow({ contact, tickets }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({
        name: contact.name,
        email: contact.email,
        phone: contact.phone ?? '',
        company: contact.company ?? '',
        notes: contact.notes ?? '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.patch(deskRoute('desk.contacts.update', workspace, { contact: contact.id }));
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Customers', href: deskRoute('desk.contacts.index', workspace) },
                { title: contact.name, href: deskRoute('desk.contacts.show', workspace, { contact: contact.id }) },
            ]}
        >
            <Head title={contact.name} />
            <div className="grid gap-6 p-4 lg:grid-cols-[360px_minmax(0,1fr)] md:p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Profile</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3">
                            <Field label="Name" error={form.errors.name}>
                                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                            </Field>
                            <Field label="Email" error={form.errors.email}>
                                <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                            </Field>
                            <Field label="Phone" error={form.errors.phone}>
                                <Input value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                            </Field>
                            <Field label="Company" error={form.errors.company}>
                                <Input value={form.data.company} onChange={(event) => form.setData('company', event.target.value)} />
                            </Field>
                            <Field label="Notes" error={form.errors.notes}>
                                <Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                            </Field>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={form.processing}>
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => {
                                        if (confirm('Delete this customer and all of their tickets?')) {
                                            router.delete(deskRoute('desk.contacts.destroy', workspace, { contact: contact.id }));
                                        }
                                    }}
                                >
                                    Delete
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tickets ({tickets.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {tickets.length === 0 && <p className="text-muted-foreground text-sm">No tickets yet.</p>}
                        {tickets.map((ticket) => (
                            <Link key={ticket.id} href={deskRoute('desk.tickets.show', workspace, { ticket: ticket.id })} className="hover:bg-muted/50 flex items-start justify-between gap-3 rounded-lg border p-3">
                                <div>
                                    <p className="font-medium">
                                        #{ticket.number} {ticket.subject}
                                    </p>
                                    <p className="text-muted-foreground text-xs">{relativeTime(ticket.created_at)}</p>
                                    <div className="mt-1 flex flex-wrap gap-1">
                                        {ticket.tags?.map((tag) => (
                                            <TagChip key={tag.id} tag={tag} />
                                        ))}
                                    </div>
                                </div>
                                <div className="flex flex-col items-end gap-1">
                                    <StatusBadge status={ticket.status} />
                                    <PriorityBadge priority={ticket.priority} />
                                </div>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
