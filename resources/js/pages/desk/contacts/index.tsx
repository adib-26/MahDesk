import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { Contact, Paginated, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    contacts: Paginated<Contact>;
    filters: { q?: string };
}

export default function ContactsIndex({ contacts, filters }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', email: '', phone: '', company: '', notes: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(deskRoute('desk.contacts.store', workspace), {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Customers', href: deskRoute('desk.contacts.index', workspace) }]}>
            <Head title="Customers" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Customers</h1>
                        <p className="text-muted-foreground text-sm">People who have reached your workspace.</p>
                    </div>
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus /> Add customer
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New customer</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={submit} className="flex flex-col gap-3">
                                <Field label="Name" error={form.errors.name}>
                                    <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                                </Field>
                                <Field label="Email" error={form.errors.email}>
                                    <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
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
                                <Button type="submit" disabled={form.processing}>
                                    Save customer
                                </Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <Input
                    placeholder="Search name, email, or company"
                    defaultValue={filters.q}
                    onKeyDown={(event) =>
                        event.key === 'Enter' &&
                        router.get(deskRoute('desk.contacts.index', workspace), { q: event.currentTarget.value }, { preserveState: true, replace: true })
                    }
                />

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Company</th>
                                <th className="px-4 py-3 font-medium">Tickets</th>
                            </tr>
                        </thead>
                        <tbody>
                            {contacts.data.map((contact) => (
                                <tr key={contact.id} className="hover:bg-muted/40 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={deskRoute('desk.contacts.show', workspace, { contact: contact.id })} className="font-medium hover:underline">
                                            {contact.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{contact.email}</td>
                                    <td className="px-4 py-3">{contact.company ?? '—'}</td>
                                    <td className="px-4 py-3">{contact.tickets_count ?? 0}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination collection={contacts} />
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
