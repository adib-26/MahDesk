import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PlatformWorkspace {
    id: number;
    name: string;
    slug: string;
    members_count: number;
    tickets_count: number;
    created_at: string;
}

export default function PlatformWorkspaces({ workspaces }: { workspaces: PlatformWorkspace[] }) {
    const form = useForm({
        name: '',
        owner_name: '',
        owner_email: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('platform.workspaces.store'), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Platform', href: route('platform.workspaces.index') }]}>
            <Head title="Organizations" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Organizations</h1>
                    <p className="text-muted-foreground text-sm">Create tenants and invite their organization owners. Super admins stay outside workspace membership.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>New organization</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Workspace name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    autoComplete="organization"
                                    required
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="owner_name">Owner name</Label>
                                <Input
                                    id="owner_name"
                                    name="owner_name"
                                    autoComplete="name"
                                    required
                                    value={form.data.owner_name}
                                    onChange={(event) => form.setData('owner_name', event.target.value)}
                                />
                                <InputError message={form.errors.owner_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="owner_email">Owner email</Label>
                                <Input
                                    id="owner_email"
                                    name="owner_email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={form.data.owner_email}
                                    onChange={(event) => form.setData('owner_email', event.target.value)}
                                />
                                <InputError message={form.errors.owner_email} />
                            </div>
                            <div className="md:col-span-3">
                                <Button type="submit" disabled={form.processing}>
                                    Create and send owner invite
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>All workspaces</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {workspaces.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No organizations yet.</p>
                        ) : (
                            workspaces.map((workspace) => (
                                <div key={workspace.id} className="flex flex-col justify-between gap-3 rounded-lg border p-3 md:flex-row md:items-center">
                                    <div>
                                        <p className="font-medium">{workspace.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {workspace.members_count} members · {workspace.tickets_count} tickets · {workspace.slug}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('desk.dashboard', workspace.slug)}>Open desk</Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm(`Delete ${workspace.name}? This cannot be undone.`)) {
                                                    router.delete(route('platform.workspaces.destroy', workspace.id));
                                                }
                                            }}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
