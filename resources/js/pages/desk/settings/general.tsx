import { SettingsNav } from '@/components/settings-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function WorkspaceGeneral({ isOwner }: { isOwner: boolean }) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({ name: workspace.name });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.patch(deskRoute('desk.settings.update', workspace));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Settings', href: deskRoute('desk.settings.general', workspace) }]}>
            <Head title="Workspace settings" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Workspace settings</h1>
                    <p className="text-muted-foreground text-sm">Manage the workspace name, agents, SLAs, and automations.</p>
                </div>
                <SettingsNav />
                <Card>
                    <CardHeader>
                        <CardTitle>General</CardTitle>
                        <CardDescription>
                            Public help center:{' '}
                            <a className="underline" href={route('help.index', workspace.slug)} target="_blank" rel="noreferrer">
                                /help/{workspace.slug}
                            </a>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3">
                            <div className="grid gap-2">
                                <Label>Workspace name</Label>
                                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                {isOwner && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Danger zone</CardTitle>
                            <CardDescription>Deleting a workspace removes tickets, customers, and articles permanently.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (confirm('Delete this workspace and all of its data?')) {
                                        router.delete(deskRoute('desk.settings.destroy', workspace));
                                    }
                                }}
                            >
                                Delete workspace
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
