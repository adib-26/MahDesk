import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { currentWorkspace } = usePage<SharedData>().props;

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-sm">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="font-display truncate text-base leading-tight font-semibold tracking-tight">
                    {currentWorkspace?.name ?? 'MahbubDesk'}
                </span>
                <span className="stamp-label text-muted-foreground truncate text-[9px]">
                    {currentWorkspace ? 'Workspace' : 'Support ledger'}
                </span>
            </div>
        </>
    );
}
