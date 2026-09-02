import type { TicketChannel, TicketPriority, TicketStatus } from '@/types';

export function deskRoute(name: string, workspace: { slug: string }, params: Record<string, string | number> = {}) {
    return route(name, { workspace: workspace.slug, ...params });
}

export const STATUS_LABELS: Record<TicketStatus, string> = {
    open: 'Open',
    pending: 'Pending',
    on_hold: 'On hold',
    resolved: 'Resolved',
    closed: 'Closed',
};

export const PRIORITY_LABELS: Record<TicketPriority, string> = {
    low: 'Low',
    normal: 'Normal',
    high: 'High',
    urgent: 'Urgent',
};

export const CHANNEL_LABELS: Record<TicketChannel, string> = {
    email: 'Email',
    web: 'Web',
    chat: 'Chat',
    phone: 'Phone',
};

export function formatMinutes(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined) {
        return '—';
    }

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours < 48) {
        return rest ? `${hours}h ${rest}m` : `${hours}h`;
    }

    const days = Math.floor(hours / 24);
    const leftoverHours = hours % 24;

    return leftoverHours ? `${days}d ${leftoverHours}h` : `${days}d`;
}

export function relativeTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    const diff = Date.now() - date.getTime();
    const minutes = Math.round(Math.abs(diff) / 60000);
    const suffix = diff >= 0 ? 'ago' : 'from now';

    if (minutes < 1) {
        return 'just now';
    }
    if (minutes < 60) {
        return `${minutes}m ${suffix}`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 48) {
        return `${hours}h ${suffix}`;
    }

    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}
