import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PendingSetup {
    manualKey: string;
    otpauthUri: string;
}

interface TwoFactorProps {
    twoFactorEnabled: boolean;
    confirmedAt: string | null;
    setup: PendingSetup | null;
    recoveryCodes: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-factor authentication',
        href: '/settings/two-factor',
    },
];

function formatConfirmedAt(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function TwoFactorAuthentication({ twoFactorEnabled, confirmedAt, setup, recoveryCodes }: TwoFactorProps) {
    const startSetup = useForm({});
    const confirmation = useForm({ code: '' });
    const recoveryCodeRegeneration = useForm({ current_password: '' });
    const disable = useForm({ current_password: '' });

    const confirmSetup: FormEventHandler = (event) => {
        event.preventDefault();

        confirmation.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirmation.reset('code'),
        });
    };

    const regenerateRecoveryCodes: FormEventHandler = (event) => {
        event.preventDefault();

        recoveryCodeRegeneration.post(route('two-factor.recovery-codes.regenerate'), {
            preserveScroll: true,
            onSuccess: () => recoveryCodeRegeneration.reset(),
        });
    };

    const disableTwoFactor: FormEventHandler = (event) => {
        event.preventDefault();

        disable.delete(route('two-factor.disable'), {
            preserveScroll: true,
            onSuccess: () => disable.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-factor authentication" />

            <SettingsLayout>
                {recoveryCodes.length > 0 && (
                    <Alert>
                        <AlertTitle>Save your recovery codes</AlertTitle>
                        <AlertDescription className="space-y-4">
                            <p>
                                Each code can be used once if you lose access to your authenticator. They are shown only now, so store them in a
                                secure password manager.
                            </p>
                            <div className="bg-muted/40 grid gap-2 rounded-md border p-4 font-mono text-sm sm:grid-cols-2">
                                {recoveryCodes.map((code) => (
                                    <code key={code}>{code}</code>
                                ))}
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                {!twoFactorEnabled && !setup && (
                    <div className="space-y-6">
                        <HeadingSmall
                            title="Two-factor authentication"
                            description="Add an authenticator app to require a time-based verification code when you sign in"
                        />

                        <div className="text-muted-foreground rounded-lg border p-4 text-sm">
                            Two-factor authentication is currently off. You can use any TOTP-compatible authenticator app.
                        </div>

                        <Button
                            type="button"
                            disabled={startSetup.processing}
                            onClick={() => startSetup.post(route('two-factor.start'), { preserveScroll: true })}
                        >
                            {startSetup.processing ? 'Starting setup…' : 'Set up two-factor authentication'}
                        </Button>
                    </div>
                )}

                {!twoFactorEnabled && setup && (
                    <div className="space-y-6">
                        <HeadingSmall
                            title="Set up your authenticator"
                            description="Add this account in your authenticator app, then confirm the current code"
                        />

                        <div className="space-y-4 rounded-lg border p-4">
                            <div className="space-y-2">
                                <Label>Manual setup key</Label>
                                <code className="bg-muted block rounded-md px-3 py-2 font-mono text-sm break-all">{setup.manualKey}</code>
                            </div>

                            <details className="space-y-2 text-sm">
                                <summary className="cursor-pointer font-medium">Show the authenticator setup URI</summary>
                                <code className="bg-muted block rounded-md px-3 py-2 font-mono text-xs break-all">{setup.otpauthUri}</code>
                            </details>
                        </div>

                        <form onSubmit={confirmSetup} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="code">Authenticator code</Label>
                                <Input
                                    id="code"
                                    value={confirmation.data.code}
                                    onChange={(event) => confirmation.setData('code', event.target.value)}
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    pattern="[0-9]{6}"
                                    maxLength={6}
                                    placeholder="123456"
                                />
                                <InputError message={confirmation.errors.code} />
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button disabled={confirmation.processing}>{confirmation.processing ? 'Confirming…' : 'Confirm and enable'}</Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={startSetup.processing}
                                    onClick={() => startSetup.post(route('two-factor.start'), { preserveScroll: true })}
                                >
                                    Start over
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {twoFactorEnabled && (
                    <>
                        <div className="space-y-6">
                            <HeadingSmall
                                title="Two-factor authentication is enabled"
                                description={confirmedAt ? `Enabled ${formatConfirmedAt(confirmedAt)}` : 'An authenticator app protects your account'}
                            />

                            <div className="text-muted-foreground rounded-lg border p-4 text-sm">
                                You will need a code from your authenticator app when signing in. Keep your recovery codes somewhere safe.
                            </div>
                        </div>

                        <div className="space-y-6">
                            <HeadingSmall
                                title="Regenerate recovery codes"
                                description="Creating new codes immediately invalidates all existing recovery codes"
                            />

                            <form onSubmit={regenerateRecoveryCodes} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="recovery_current_password">Current password</Label>
                                    <Input
                                        id="recovery_current_password"
                                        type="password"
                                        value={recoveryCodeRegeneration.data.current_password}
                                        onChange={(event) => recoveryCodeRegeneration.setData('current_password', event.target.value)}
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                    />
                                    <InputError message={recoveryCodeRegeneration.errors.current_password} />
                                </div>

                                <Button disabled={recoveryCodeRegeneration.processing} variant="outline">
                                    {recoveryCodeRegeneration.processing ? 'Regenerating…' : 'Regenerate recovery codes'}
                                </Button>
                            </form>
                        </div>

                        <div className="space-y-6">
                            <HeadingSmall
                                title="Disable two-factor authentication"
                                description="Use your password to remove authenticator protection from this account"
                            />

                            <form onSubmit={disableTwoFactor} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="disable_current_password">Current password</Label>
                                    <Input
                                        id="disable_current_password"
                                        type="password"
                                        value={disable.data.current_password}
                                        onChange={(event) => disable.setData('current_password', event.target.value)}
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                    />
                                    <InputError message={disable.errors.current_password} />
                                </div>

                                <Button disabled={disable.processing} variant="destructive">
                                    {disable.processing ? 'Disabling…' : 'Disable two-factor authentication'}
                                </Button>
                            </form>
                        </div>
                    </>
                )}
            </SettingsLayout>
        </AppLayout>
    );
}
