import InputError from '@/components/input-error';
import { SettingsNav } from '@/components/settings-nav';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Props {
    isOwner: boolean;
    email: { inboundAddress: string; supportEmail: string | null; provider: string; configured: boolean };
}

export default function WorkspaceGeneral({ isOwner, email }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({ name: workspace.name, support_email: email.supportEmail ?? '' });
    const [copied, setCopied] = useState(false);

    const copyInboundAddress = async () => {
        await navigator.clipboard.writeText(email.inboundAddress);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.patch(deskRoute('desk.settings.update', workspace));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Settings', href: deskRoute('desk.settings.general', workspace) }]}>
            <Head title="Workspace settings" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-3xl font-semibold tracking-tight">Workspace settings</h1>
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
                            <div className="grid gap-2">
                                <Label htmlFor="support_email">Support email address</Label>
                                <Input
                                    id="support_email"
                                    type="email"
                                    placeholder="support@company.com"
                                    value={form.data.support_email}
                                    onChange={(event) => form.setData('support_email', event.target.value)}
                                />
                                <p className="text-muted-foreground text-xs">
                                    The address your customers write to. Customer replies are sent back to this address.
                                </p>
                                <InputError message={form.errors.support_email} />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Email-to-ticket</CardTitle>
                        <CardDescription>
                            Forward your support address to this routing address. New emails open tickets, and replies containing the ticket reference
                            (for example <span className="font-mono">[#42]</span>) are added to the existing ticket.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {!email.configured && (
                            <p className="rounded-sm border border-amber-800/30 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-300/25 dark:bg-amber-950/30 dark:text-amber-200">
                                {email.provider === 'testmail'
                                    ? 'Email import is off. Set TESTMAIL_NAMESPACE and TESTMAIL_API_KEY in .env, then run php artisan desk:testmail-status --check.'
                                    : 'Email import is off. Set INBOUND_EMAIL_SECRET in .env and point your provider webhook at /api/v1/inbound/email/postmark.'}
                            </p>
                        )}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <code className="bg-muted flex-1 rounded-sm border px-3 py-2 font-mono text-sm break-all">{email.inboundAddress}</code>
                            <Button type="button" variant="outline" onClick={copyInboundAddress}>
                                {copied ? 'Copied' : 'Copy address'}
                            </Button>
                        </div>
                        {email.provider === 'testmail' && (
                            <p className="text-muted-foreground text-xs">
                                Received through testmail.app and imported every minute. For local testing, run <code>php artisan desk:poll-testmail --sync</code>.
                            </p>
                        )}
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
