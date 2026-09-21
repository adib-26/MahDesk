import { ChannelBadge, PriorityBadge, StatusBadge } from '@/components/desk-badges';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { deskRoute, formatMinutes, relativeTime } from '@/lib/desk';
import { type Analytics, type SharedData, type Ticket } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, CheckCircle2, Clock3, MessageSquareText, ShieldAlert, Star, TicketCheck, Tickets } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis } from 'recharts';

interface Props {
    analytics: Analytics;
    recentTickets: Ticket[];
}

const volumeChartConfig = {
    created: {
        label: 'Created',
        color: 'var(--chart-1)',
    },
    resolved: {
        label: 'Resolved',
        color: 'var(--chart-2)',
    },
} satisfies ChartConfig;

function Metric({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: string | number | React.ReactNode;
    hint: string;
    icon: LucideIcon;
    tone?: 'default' | 'warning' | 'success';
}) {
    const iconTone = {
        default: 'bg-muted text-muted-foreground',
        warning: 'bg-red-500/10 text-red-700 dark:text-red-300',
        success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    }[tone];

    return (
        <Card className="shadow-none">
            <CardHeader className="gap-3 p-5 pb-2">
                <div className="flex items-center justify-between gap-3">
                    <CardDescription className="stamp-label text-[10px]">{label}</CardDescription>
                    <span className={`flex size-8 items-center justify-center rounded-sm ${iconTone}`}>
                        <Icon className="size-4" aria-hidden="true" />
                    </span>
                </div>
                <CardTitle className="font-display text-3xl font-medium tracking-tight tabular-nums">{value}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground min-h-10 px-5 pb-5 font-mono text-[11px]">{hint}</CardContent>
        </Card>
    );
}

function formatShortDate(value: string) {
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${value}T00:00:00`));
}

function Percentage({ value }: { value: number | null }) {
    return <>{value === null ? '—' : `${value}%`}</>;
}

export default function DeskDashboard({ analytics, recentTickets }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const createdInPeriod = analytics.series.reduce((total, day) => total + day.created, 0);
    const resolvedInPeriod = analytics.series.reduce((total, day) => total + day.resolved, 0);
    const netTicketChange = createdInPeriod - resolvedInPeriod;

    return (
        <AppLayout breadcrumbs={[{ title: 'Analytics', href: deskRoute('desk.dashboard', workspace) }]}>
            <Head title="Support analytics" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div>
                        <p className="stamp-label text-muted-foreground">{workspace.name} · last 30 days</p>
                        <h1 className="font-display mt-1 text-3xl font-semibold tracking-tight">Support analytics</h1>
                        <p className="text-muted-foreground mt-1 text-sm">A clear view of ticket volume, service health, and team performance.</p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={deskRoute('desk.tickets.index', workspace)}>View tickets</Link>
                    </Button>
                </div>

                <section aria-labelledby="support-metrics-heading">
                    <div className="mb-3 flex items-center justify-between gap-4">
                        <h2 id="support-metrics-heading" className="stamp-label text-muted-foreground">
                            Support metrics
                        </h2>
                        <p className="text-muted-foreground font-mono text-[11px]">Live queue + 30-day activity</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric label="Total tickets" value={analytics.kpis.totalTickets} hint="All tickets visible to you" icon={Tickets} />
                        <Metric
                            label="Open tickets"
                            value={analytics.kpis.open}
                            hint={`${analytics.kpis.unassigned} unassigned`}
                            icon={MessageSquareText}
                        />
                        <Metric
                            label="Resolved tickets"
                            value={analytics.kpis.resolvedTickets}
                            hint="Resolved in the last 30 days"
                            icon={TicketCheck}
                            tone="success"
                        />
                        <Metric
                            label="SLA breaches"
                            value={analytics.kpis.slaBreaches}
                            hint={
                                analytics.kpis.slaBreaches === 1
                                    ? '1 ticket missed an SLA target'
                                    : `${analytics.kpis.slaBreaches} tickets missed an SLA target`
                            }
                            icon={ShieldAlert}
                            tone={analytics.kpis.slaBreaches > 0 ? 'warning' : 'default'}
                        />
                        <Metric
                            label="Average response time"
                            value={formatMinutes(analytics.kpis.avgFirstResponseMinutes)}
                            hint="First reply, last 30 days"
                            icon={Clock3}
                        />
                        <Metric
                            label="Average resolution time"
                            value={formatMinutes(analytics.kpis.avgResolutionMinutes)}
                            hint="Resolved tickets, last 30 days"
                            icon={CheckCircle2}
                        />
                        <Metric
                            label="Customer satisfaction"
                            value={<Percentage value={analytics.kpis.customerSatisfaction} />}
                            hint={analytics.kpis.csatResponses > 0 ? `${analytics.kpis.csatResponses} survey responses` : 'No feedback collected yet'}
                            icon={Star}
                        />
                    </div>
                </section>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card className="shadow-none xl:col-span-2">
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="font-display text-xl font-semibold tracking-tight">Ticket volume</CardTitle>
                                <CardDescription className="mt-1">Created and resolved tickets over the last 30 days.</CardDescription>
                            </div>
                            <div className="hidden text-right sm:block">
                                <p className="font-mono text-lg font-medium tabular-nums">{createdInPeriod}</p>
                                <p className="stamp-label text-muted-foreground text-[9px]">Created</p>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer config={volumeChartConfig} className="h-[270px] w-full">
                                <BarChart accessibilityLayer data={analytics.series} margin={{ top: 12, right: 6, left: -20, bottom: 0 }}>
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="date"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        minTickGap={28}
                                        tickFormatter={formatShortDate}
                                    />
                                    <ChartTooltip
                                        cursor={false}
                                        content={<ChartTooltipContent labelFormatter={(value) => formatShortDate(String(value))} />}
                                    />
                                    <Bar dataKey="created" fill="var(--color-created)" radius={[2, 2, 0, 0]} maxBarSize={22} />
                                    <Bar dataKey="resolved" fill="var(--color-resolved)" radius={[2, 2, 0, 0]} maxBarSize={22} />
                                    <ChartLegend content={<ChartLegendContent />} />
                                </BarChart>
                            </ChartContainer>
                            <div className="text-muted-foreground mt-3 flex flex-wrap gap-x-5 gap-y-1 border-t pt-3 font-mono text-[11px]">
                                <span>{createdInPeriod} created</span>
                                <span>{resolvedInPeriod} resolved</span>
                                <span>{netTicketChange >= 0 ? `+${netTicketChange}` : netTicketChange} net change</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-xl font-semibold tracking-tight">SLA health</CardTitle>
                            <CardDescription>
                                First-response and resolution targets on the live queue and tickets from the last 30 days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="bg-muted/30 rounded-sm border p-4">
                                <p className="stamp-label text-muted-foreground text-[10px]">SLA compliance</p>
                                <p className="font-display mt-2 text-4xl font-medium tracking-tight tabular-nums">
                                    <Percentage value={analytics.kpis.slaCompliance} />
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Share of those tickets that have not missed a first-response or resolution target.
                                </p>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-sm border p-3">
                                    <div className="flex items-center gap-2 text-red-700 dark:text-red-300">
                                        <AlertTriangle className="size-4" aria-hidden="true" />
                                        <p className="stamp-label text-[9px]">Breached</p>
                                    </div>
                                    <p className="mt-2 text-2xl font-medium tabular-nums">{analytics.kpis.slaBreaches}</p>
                                    <p className="text-muted-foreground mt-1 text-[11px]">Missed a first-response or resolution target</p>
                                </div>
                                <div className="rounded-sm border p-3">
                                    <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300">
                                        <Clock3 className="size-4" aria-hidden="true" />
                                        <p className="stamp-label text-[9px]">At risk</p>
                                    </div>
                                    <p className="mt-2 text-2xl font-medium tabular-nums">{analytics.kpis.breachingSoon}</p>
                                    <p className="text-muted-foreground mt-1 text-[11px]">Unanswered first response due within two hours</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-xl font-semibold tracking-tight">Open ticket mix</CardTitle>
                            <CardDescription>Current queue by status and priority.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            <div className="flex flex-col gap-3">
                                {analytics.byStatus.length === 0 && <p className="text-muted-foreground text-sm">No open tickets.</p>}
                                {analytics.byStatus.map((row) => (
                                    <div key={row.name} className="flex items-center justify-between gap-3 text-sm">
                                        <StatusBadge status={row.name as Ticket['status']} />
                                        <span className="font-mono font-medium tabular-nums">{row.count}</span>
                                    </div>
                                ))}
                            </div>
                            {analytics.byPriority.length > 0 && (
                                <div className="border-t pt-4">
                                    <p className="stamp-label text-muted-foreground mb-3 text-[10px]">Priority</p>
                                    <div className="flex flex-col gap-3">
                                        {analytics.byPriority.map((row) => (
                                            <div key={row.name} className="flex items-center justify-between gap-3 text-sm">
                                                <PriorityBadge priority={row.name as Ticket['priority']} />
                                                <span className="font-mono font-medium tabular-nums">{row.count}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-none xl:col-span-2">
                        <CardHeader>
                            <CardTitle className="font-display text-xl font-semibold tracking-tight">Team performance</CardTitle>
                            <CardDescription>Resolution output and average resolution time in the last 30 days.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {analytics.agents.length === 0 && <p className="text-muted-foreground text-sm">No support team members to report yet.</p>}
                            {analytics.agents.map((agent) => (
                                <div
                                    key={agent.id}
                                    className="grid gap-3 rounded-sm border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{agent.name}</p>
                                        <p className="text-muted-foreground mt-0.5 text-xs capitalize">
                                            {agent.role} · {agent.openTickets} open
                                        </p>
                                    </div>
                                    <div className="sm:text-right">
                                        <p className="font-mono font-medium tabular-nums">{agent.resolvedLast30Days}</p>
                                        <p className="stamp-label text-muted-foreground text-[9px]">Resolved</p>
                                    </div>
                                    <div className="sm:w-24 sm:text-right">
                                        <p className="font-mono font-medium tabular-nums">{formatMinutes(agent.avgResolutionMinutes)}</p>
                                        <p className="stamp-label text-muted-foreground text-[9px]">Avg. resolve</p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-none">
                    <CardHeader className="flex-row items-center justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle className="font-display text-xl font-semibold tracking-tight">Recent tickets</CardTitle>
                            <CardDescription className="mt-1">Newest activity in this workspace.</CardDescription>
                        </div>
                        <Link href={deskRoute('desk.tickets.index', workspace)} className="text-sm font-medium hover:underline">
                            All tickets
                        </Link>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {recentTickets.length === 0 && <p className="text-muted-foreground text-sm">No tickets yet.</p>}
                        {recentTickets.map((ticket) => (
                            <Link
                                key={ticket.id}
                                href={deskRoute('desk.tickets.show', workspace, { ticket: ticket.id })}
                                className="hover:bg-muted/60 flex items-start justify-between gap-3 rounded-sm border p-3 transition-colors"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        <span className="text-stamp font-mono text-xs">#{ticket.number}</span> {ticket.subject}
                                    </p>
                                    <p className="text-muted-foreground mt-1 truncate text-xs">
                                        {ticket.contact?.name} · {relativeTime(ticket.created_at)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <StatusBadge status={ticket.status} />
                                    <ChannelBadge channel={ticket.channel} />
                                </div>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
