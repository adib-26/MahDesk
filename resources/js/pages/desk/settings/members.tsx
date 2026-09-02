import { SettingsNav } from '@/components/settings-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    can_view_unassigned: boolean;
    joined_at?: string;
    open_tickets: number;
}

interface Invitation {
    id: number;
    name: string | null;
    email: string;
    role: string;
    can_view_unassigned: boolean;
    expires_at: string;
}

export default function MembersSettings({ members, invitations = [] }: { members: Member[]; invitations?: Invitation[] }) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({ name: '', email: '', role: 'agent', can_view_unassigned: false });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(deskRoute('desk.members.store', workspace), { onSuccess: () => form.reset('name', 'email') });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Agents', href: deskRoute('desk.members.index', workspace) }]}>
            <Head title="Agents" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Agents</h1>
                    <p className="text-muted-foreground text-sm">Invite organization admins, managers, and agents. Customers register themselves.</p>
                </div>
                <SettingsNav />
                <Card>
                    <CardHeader>
                        <CardTitle>Invite teammate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-3 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    autoComplete="name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role">Role</Label>
                                <Select value={form.data.role} onValueChange={(value) => form.setData('role', value)}>
                                    <SelectTrigger id="role">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="admin">Organization Admin</SelectItem>
                                            <SelectItem value="manager">Manager</SelectItem>
                                            <SelectItem value="agent">Support Agent</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            {form.data.role === 'agent' && (
                                <div className="flex items-center gap-3 self-end pb-2">
                                    <Checkbox
                                        id="can_view_unassigned"
                                        checked={form.data.can_view_unassigned}
                                        onCheckedChange={(checked) => form.setData('can_view_unassigned', checked === true)}
                                    />
                                    <Label htmlFor="can_view_unassigned">Can see unassigned tickets in their team queue</Label>
                                </div>
                            )}
                            <div className="md:col-span-2">
                                <Button type="submit" disabled={form.processing}>
                                    Send invitation
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                {invitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending invitations</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {invitations.map((invitation) => (
                                <div key={invitation.id} className="flex flex-col justify-between gap-3 rounded-lg border p-3 md:flex-row md:items-center">
                                    <div>
                                        <p className="font-medium">{invitation.name || invitation.email}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {invitation.email} · {invitation.role}
                                            {invitation.can_view_unassigned ? ' · team unassigned queue' : ''}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.delete(deskRoute('desk.invitations.destroy', workspace, { invitation: invitation.id }))}
                                    >
                                        Revoke
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Members</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {members.map((member) => (
                            <div key={member.id} className="flex flex-col justify-between gap-3 rounded-lg border p-3 md:flex-row md:items-center">
                                <div>
                                    <p className="font-medium">{member.name}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {member.email} · {member.open_tickets} open tickets
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {member.role === 'owner' ? (
                                        <span className="text-sm">Owner</span>
                                    ) : (
                                        <>
                                            <Select
                                                value={member.role}
                                                onValueChange={(value) =>
                                                    router.patch(
                                                        deskRoute('desk.members.update', workspace, { member: member.id }),
                                                        { role: value, can_view_unassigned: member.can_view_unassigned },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-44">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="admin">Organization Admin</SelectItem>
                                                        <SelectItem value="manager">Manager</SelectItem>
                                                        <SelectItem value="agent">Support Agent</SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            {member.role === 'agent' && (
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={member.can_view_unassigned}
                                                        onCheckedChange={(checked) =>
                                                            router.patch(
                                                                deskRoute('desk.members.update', workspace, { member: member.id }),
                                                                { role: member.role, can_view_unassigned: checked === true },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    />
                                                    Team queue
                                                </label>
                                            )}
                                        </>
                                    )}
                                    {member.role !== 'owner' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm(`Remove ${member.name}?`)) {
                                                    router.delete(deskRoute('desk.members.destroy', workspace, { member: member.id }));
                                                }
                                            }}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
