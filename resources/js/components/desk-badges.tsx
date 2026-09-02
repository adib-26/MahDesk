import { Badge } from '@/components/ui/badge';
import { CHANNEL_LABELS, PRIORITY_LABELS, STATUS_LABELS } from '@/lib/desk';
import { cn } from '@/lib/utils';
import type { Tag, TicketChannel, TicketPriority, TicketStatus } from '@/types';

const statusClass: Record<TicketStatus, string> = {
    open: 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    pending: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    on_hold: 'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    resolved: 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    closed: 'border-transparent bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
};

const priorityClass: Record<TicketPriority, string> = {
    low: 'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    normal: 'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    high: 'border-transparent bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
    urgent: 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
};

export function StatusBadge({ status }: { status: TicketStatus }) {
    return <Badge className={cn('capitalize', statusClass[status])}>{STATUS_LABELS[status]}</Badge>;
}

export function PriorityBadge({ priority }: { priority: TicketPriority }) {
    return <Badge className={cn('capitalize', priorityClass[priority])}>{PRIORITY_LABELS[priority]}</Badge>;
}

export function ChannelBadge({ channel }: { channel: TicketChannel }) {
    return <Badge variant="outline">{CHANNEL_LABELS[channel]}</Badge>;
}

export function TagChip({ tag }: { tag: Tag }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs" style={{ borderColor: tag.color }}>
            <span className="size-1.5 rounded-full" style={{ backgroundColor: tag.color }} />
            {tag.name}
        </span>
    );
}
