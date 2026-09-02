import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

interface BrowserSession {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_active_at: string;
    is_current: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sessions',
        href: '/settings/sessions',
    },
];

function formatLastActive(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function Sessions({ sessions }: { sessions: BrowserSession[] }) {
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [revokingSessionId, setRevokingSessionId] = useState<string | null>(null);
    const { data, setData, delete: destroyOther, errors, processing, reset } = useForm({ current_password: '' });

    const revokeSession = (session: BrowserSession) => {
        setRevokingSessionId(session.id);

        router.delete(route('sessions.destroy', { session: session.id }), {
            preserveScroll: true,
            onFinish: () => setRevokingSessionId(null),
        });
    };

    const revokeOtherSessions: FormEventHandler = (event) => {
        event.preventDefault();

        destroyOther(route('sessions.destroy-other'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => currentPasswordInput.current?.focus(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sessions" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Browser sessions" description="Manage the devices currently signed in to your account" />

                    <div className="space-y-3">
                        {sessions.length === 0 ? (
                            <p className="text-muted-foreground rounded-lg border p-4 text-sm">No active sessions were found.</p>
                        ) : (
                            sessions.map((session) => (
                                <div
                                    key={session.id}
                                    className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="truncate text-sm font-medium">{session.user_agent || 'Unknown browser'}</p>
                                            {session.is_current && (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                                    Current session
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground text-sm">
                                            {session.ip_address || 'Unknown IP address'} · Last active {formatLastActive(session.last_active_at)}
                                        </p>
                                    </div>

                                    <Button
                                        type="button"
                                        variant={session.is_current ? 'destructive' : 'outline'}
                                        size="sm"
                                        disabled={revokingSessionId === session.id}
                                        onClick={() => revokeSession(session)}
                                    >
                                        {revokingSessionId === session.id ? 'Signing out…' : session.is_current ? 'Sign out' : 'Revoke'}
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                <div className="space-y-6">
                    <HeadingSmall title="Sign out other sessions" description="Enter your password to sign out every session except this one" />

                    <form onSubmit={revokeOtherSessions} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">Current password</Label>
                            <Input
                                id="current_password"
                                ref={currentPasswordInput}
                                type="password"
                                value={data.current_password}
                                onChange={(event) => setData('current_password', event.target.value)}
                                autoComplete="current-password"
                                placeholder="Current password"
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <Button disabled={processing}>Sign out other sessions</Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
