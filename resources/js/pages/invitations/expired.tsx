import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';
import { Head } from '@inertiajs/react';

export default function InvitationExpired() {
    return (
        <AuthLayout title="Invitation unavailable" description="This invitation is invalid or has expired. Ask an organization admin to send a new one.">
            <Head title="Invitation expired" />
            <TextLink href={route('login')}>Back to sign in</TextLink>
        </AuthLayout>
    );
}
