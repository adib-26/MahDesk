import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge({ canUseRecoveryCode }: { canUseRecoveryCode: boolean }) {
    const [usingRecoveryCode, setUsingRecoveryCode] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        post(route('two-factor.challenge.store'), {
            onFinish: () => reset(usingRecoveryCode ? 'recovery_code' : 'code'),
        });
    };

    const switchMethod = () => {
        setUsingRecoveryCode((current) => !current);
        reset('code', 'recovery_code');
    };

    return (
        <AuthLayout
            title="Verify your identity"
            description={usingRecoveryCode ? 'Enter one of your saved recovery codes to finish signing in.' : 'Enter the six-digit code from your authenticator app.'}
        >
            <Head title="Two-factor authentication" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-3">
                    {usingRecoveryCode ? (
                        <>
                            <Label htmlFor="recovery_code">Recovery code</Label>
                            <Input
                                id="recovery_code"
                                autoFocus
                                autoComplete="one-time-code"
                                value={data.recovery_code}
                                onChange={(event) => setData('recovery_code', event.target.value)}
                                placeholder="ABCD-1234-EFGH-5678"
                            />
                            <InputError message={errors.recovery_code} />
                        </>
                    ) : (
                        <>
                            <Label htmlFor="code">Authentication code</Label>
                            <Input
                                id="code"
                                autoFocus
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={data.code}
                                onChange={(event) => setData('code', event.target.value.replace(/\D/g, ''))}
                                placeholder="123456"
                            />
                            <InputError message={errors.code} />
                        </>
                    )}
                </div>

                <Button type="submit" disabled={processing} className="w-full">
                    Verify and sign in
                </Button>

                <div className="flex flex-col gap-3 text-center text-sm">
                    {canUseRecoveryCode && (
                        <button type="button" className="text-muted-foreground underline" onClick={switchMethod}>
                            {usingRecoveryCode ? 'Use an authenticator code instead' : 'Use a recovery code instead'}
                        </button>
                    )}
                    <TextLink href={route('login')}>Back to sign in</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
