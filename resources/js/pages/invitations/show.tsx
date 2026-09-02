import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Invitation {
    token: string;
    email: string;
    name: string | null;
    role: string;
    workspace: { name: string };
    expires_at: string;
}

export default function InvitationShow({
    invitation,
    authenticatedEmail,
    emailMatches,
}: {
    invitation: Invitation;
    authenticatedEmail: string | null;
    emailMatches: boolean;
}) {
    const form = useForm({
        name: invitation.name ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('invitations.accept', invitation.token), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={`Join ${invitation.workspace.name}`}
            description={`You were invited as ${invitation.role}. Use ${invitation.email} to accept.`}
        >
            <Head title="Accept invitation" />

            {authenticatedEmail && !emailMatches ? (
                <p className="text-muted-foreground text-sm">
                    You are signed in as {authenticatedEmail}. Sign out and use {invitation.email} to accept this invitation.
                </p>
            ) : authenticatedEmail && emailMatches ? (
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <InputError message={form.errors.email} />
                    <Button type="submit" disabled={form.processing}>
                        Join workspace
                    </Button>
                </form>
            ) : (
                <form className="flex flex-col gap-6" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>
                        <Input
                            id="name"
                            name="name"
                            autoComplete="name"
                            autoFocus
                            required
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" name="email" type="email" autoComplete="username" value={invitation.email} readOnly />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-password">Password</Label>
                        <Input
                            id="new-password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        <InputError message={form.errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        />
                        <InputError message={form.errors.password_confirmation} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Create account and join
                    </Button>
                    <p className="text-muted-foreground text-center text-sm">
                        Already have an account? <TextLink href={route('login')}>Sign in</TextLink>
                    </p>
                </form>
            )}
        </AuthLayout>
    );
}
