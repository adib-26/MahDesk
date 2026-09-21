import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="paper-grain bg-background flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-5">
                        <Link href={route('home')} className="flex flex-col items-center gap-3 font-medium">
                            <div className="bg-primary text-primary-foreground flex size-11 items-center justify-center rounded-sm">
                                <AppLogoIcon className="size-7" />
                            </div>
                            <span className="font-display text-lg font-semibold tracking-tight">MahbubDesk</span>
                        </Link>

                        <div className="rule-double w-16" />

                        <div className="space-y-2 text-center">
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-balance">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm text-pretty">{description}</p>
                        </div>
                    </div>
                    {children}
                    <p className="text-muted-foreground text-center font-mono text-[11px] tracking-wide">
                        Support, on the record.
                    </p>
                </div>
            </div>
        </div>
    );
}
