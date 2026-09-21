import { Badge } from '@/components/ui/badge';
import { CHANNEL_LABELS, PRIORITY_LABELS, STATUS_LABELS } from '@/lib/desk';
import { cn } from '@/lib/utils';
import type { Tag, TicketChannel, TicketPriority, TicketStatus } from '@/types';

const stamp = 'rounded-sm border px-1.5 py-0.5 font-mono text-[10px] font-medium tracking-[0.1em] uppercase';

const statusClass: Record<TicketStatus, string> = {
    open: 'border-blue-800/25 bg-blue-800/8 text-blue-900 dark:border-blue-300/30 dark:bg-blue-300/10 dark:text-blue-200',
    pending: 'border-amber-800/25 bg-amber-800/8 text-amber-900 dark:border-amber-300/30 dark:bg-amber-300/10 dark:text-amber-200',
    on_hold: 'border-stone-500/30 bg-stone-500/8 text-stone-700 dark:border-stone-400/30 dark:bg-stone-400/10 dark:text-stone-300',
    resolved: 'border-emerald-800/25 bg-emerald-800/8 text-emerald-900 dark:border-emerald-300/30 dark:bg-emerald-300/10 dark:text-emerald-200',
    closed: 'border-stone-500/30 bg-stone-500/12 text-stone-600 dark:border-stone-400/30 dark:bg-stone-400/10 dark:text-stone-400',
};

const priorityClass: Record<TicketPriority, string> = {
    low: 'border-stone-500/30 bg-stone-500/8 text-stone-600 dark:border-stone-400/30 dark:bg-stone-400/10 dark:text-stone-300',
    normal: 'border-sky-800/25 bg-sky-800/8 text-sky-900 dark:border-sky-300/30 dark:bg-sky-300/10 dark:text-sky-200',
    high: 'border-orange-800/25 bg-orange-800/8 text-orange-900 dark:border-orange-300/30 dark:bg-orange-300/10 dark:text-orange-200',
    urgent: 'border-red-800/30 bg-red-800/10 text-red-900 dark:border-red-300/35 dark:bg-red-300/12 dark:text-red-200',
};

export function StatusBadge({ status }: { status: TicketStatus }) {
    return <Badge className={cn(stamp, statusClass[status])}>{STATUS_LABELS[status]}</Badge>;
}

export function PriorityBadge({ priority }: { priority: TicketPriority }) {
    return <Badge className={cn(stamp, priorityClass[priority])}>{PRIORITY_LABELS[priority]}</Badge>;
}

export function ChannelBadge({ channel }: { channel: TicketChannel }) {
    return <Badge variant="outline" className={cn(stamp, 'text-muted-foreground')}>{CHANNEL_LABELS[channel]}</Badge>;
}

export function TagChip({ tag }: { tag: Tag }) {
    return (
        <span
            className="inline-flex items-center gap-1.5 rounded-sm border px-1.5 py-0.5 font-mono text-[10px] tracking-[0.08em] uppercase"
            style={{ borderColor: tag.color }}
        >
            <span className="size-1.5 rounded-full" style={{ backgroundColor: tag.color }} />
            {tag.name}
        </span>
    );
}
