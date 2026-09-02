import { SettingsNav } from '@/components/settings-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    joined_at?: string;
    open_tickets: number;
}

export default function MembersSettings({ members }: { members: Member[] }) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({ name: '', email: '', role: 'agent' });

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
                    <p className="text-muted-foreground text-sm">Invite teammates and assign owner, admin, or agent access.</p>
                </div>
                <SettingsNav />
                <Card>
                    <CardHeader>
                        <CardTitle>Invite agent</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                            <div className="grid gap-2">
                                <Label>Name</Label>
                                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Email</Label>
                                <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Role</Label>
                                <Select value={form.data.role} onValueChange={(value) => form.setData('role', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="admin">Admin</SelectItem>
                                            <SelectItem value="agent">Agent</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" disabled={form.processing} className="w-full">
                                    Add
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
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
                                <div className="flex items-center gap-2">
                                    {member.role === 'owner' ? (
                                        <span className="text-sm capitalize">{member.role}</span>
                                    ) : (
                                        <Select
                                            value={member.role}
                                            onValueChange={(value) =>
                                                router.patch(deskRoute('desk.members.update', workspace, { member: member.id }), { role: value }, { preserveScroll: true })
                                            }
                                        >
                                            <SelectTrigger className="w-32">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="admin">Admin</SelectItem>
                                                    <SelectItem value="agent">Agent</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
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
