import { ChannelBadge, PriorityBadge, StatusBadge } from '@/components/desk-badges';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { CATEGORY_LABELS, deskRoute, formatMinutes, PRIORITY_LABELS, relativeTime } from '@/lib/desk';
import { cn } from '@/lib/utils';
import { type Analytics, type SharedData, type Ticket, type TicketCategory } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, CheckCircle2, Clock3, MessageSquareText, ShieldAlert, Star, TicketCheck, Tickets } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

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

const distributionChartConfig = {
    count: {
        label: 'Tickets',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;

const agentActivityChartConfig = {
    ticketsAssigned: {
        label: 'Assigned',
        color: 'var(--chart-4)',
    },
    ticketsResolved: {
        label: 'Resolved',
        color: 'var(--chart-2)',
    },
} satisfies ChartConfig;

function ticketsHref(workspace: { slug: string }, query: Record<string, string | number> = {}) {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(query)) {
        search.set(key, String(value));
    }
    const base = deskRoute('desk.tickets.index', workspace);

    return search.size > 0 ? `${base}?${search.toString()}` : base;
}

function Metric({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
    href,
}: {
    label: string;
    value: string | number | React.ReactNode;
    hint: string;
    icon: LucideIcon;
    tone?: 'default' | 'warning' | 'success';
    href?: string;
}) {
    const iconTone = {
        default: 'bg-muted text-muted-foreground',
        warning: 'bg-red-500/10 text-red-700 dark:text-red-300',
        success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    }[tone];

    const card = (
        <Card className={cn('shadow-none', href && 'h-full transition-colors group-hover:bg-muted/40')}>
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

    if (!href) {
        return card;
    }

    return (
        <Link href={href} className="group block rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2" aria-label={`${label}: open matching tickets`}>
            {card}
        </Link>
    );
}

function formatShortDate(value: string) {
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${value}T00:00:00`));
}

function Percentage({ value }: { value: number | null }) {
    return <>{value === null ? '—' : `${value}%`}</>;
}

function Rating({ value }: { value: number | null }) {
    return <>{value === null ? '—' : `${value.toFixed(1)} / 5`}</>;
}

export default function DeskDashboard({ analytics, recentTickets }: Props) {
    const { currentWorkspace, memberRole } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const seesOnlyTheirTickets = memberRole === 'agent' || memberRole === 'manager';
    const createdInPeriod = analytics.series.reduce((total, day) => total + day.created, 0);
    const resolvedInPeriod = analytics.series.reduce((total, day) => total + day.resolved, 0);
    const netTicketChange = createdInPeriod - resolvedInPeriod;
    const agentChartData = analytics.agents.slice(0, 8);

    return (
        <AppLayout breadcrumbs={[{ title: 'Analytics', href: deskRoute('desk.dashboard', workspace) }]}>
            <Head title="Support analytics" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div>
                        <p className="stamp-label text-muted-foreground">{workspace.name} · last 30 days</p>
                        <h1 className="font-display mt-1 text-3xl font-semibold tracking-tight">Support analytics</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {seesOnlyTheirTickets
                                ? 'Your numbers, from the tickets you can see.'
                                : 'A clear view of ticket volume, service health, and team performance.'}
                        </p>
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
                        <Metric label="Total tickets" value={analytics.kpis.totalTickets} hint="All tickets visible to you" icon={Tickets} href={ticketsHref(workspace)} />
                        <Metric
                            label="Open tickets"
                            value={analytics.kpis.open}
                            hint={`${analytics.kpis.unassigned} unassigned`}
                            icon={MessageSquareText}
                            href={ticketsHref(workspace, { view: 'active' })}
                        />
                        <Metric
                            label="Resolved tickets"
                            value={analytics.kpis.resolvedTickets}
                            hint="Resolved in the last 30 days"
                            icon={TicketCheck}
                            tone="success"
                            href={ticketsHref(workspace, { view: 'resolved' })}
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
                            href={ticketsHref(workspace, { view: 'breached' })}
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
                            href={ticketsHref(workspace, { view: 'resolved' })}
                        />
                        <Metric
                            label="Customer satisfaction"
                            value={<Percentage value={analytics.kpis.customerSatisfaction} />}
                            hint={analytics.kpis.csatResponses > 0 ? `${analytics.kpis.csatResponses} survey responses` : 'No feedback collected yet'}
                            icon={Star}
                        />
                    </div>
                </section>

                <section aria-labelledby="business-metrics-heading" className="flex flex-col gap-4">
                    <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                        <div>
                            <p className="stamp-label text-muted-foreground">Business metrics</p>
                            <h2 id="business-metrics-heading" className="font-display mt-1 text-2xl font-semibold tracking-tight">
                                Demand, priority, and resolution trends
                            </h2>
                        </div>
                        <p className="text-muted-foreground font-mono text-[11px]">Reporting period: last 30 days</p>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-3">
                        <Card className="shadow-none xl:col-span-2">
                            <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                                <div>
                                    <p className="stamp-label text-muted-foreground text-[10px]">Tickets by day</p>
                                    <CardTitle className="font-display mt-1 text-xl font-semibold tracking-tight">
                                        Ticket volume &amp; resolution trends
                                    </CardTitle>
                                    <CardDescription className="mt-1">Daily created and resolved tickets over the last 30 days.</CardDescription>
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

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-xl font-semibold tracking-tight">Tickets by category</CardTitle>
                                <CardDescription>New tickets by category during the last 30 days.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {analytics.byCategory.length === 0 ? (
                                    <p className="text-muted-foreground py-16 text-center text-sm">No ticket activity in this reporting period.</p>
                                ) : (
                                    <ChartContainer config={distributionChartConfig} className="h-[250px] w-full">
                                        <BarChart
                                            accessibilityLayer
                                            data={analytics.byCategory}
                                            layout="vertical"
                                            margin={{ top: 4, right: 12, left: 10, bottom: 0 }}
                                        >
                                            <CartesianGrid horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                                            <YAxis
                                                dataKey="name"
                                                type="category"
                                                tickLine={false}
                                                axisLine={false}
                                                tickMargin={8}
                                                width={112}
                                                tickFormatter={(value) => CATEGORY_LABELS[value as TicketCategory] ?? value}
                                            />
                                            <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                                            <Bar
                                                dataKey="count"
                                                fill="var(--color-count)"
                                                radius={[0, 2, 2, 0]}
                                                maxBarSize={26}
                                                className="cursor-pointer"
                                                onClick={(bar) => {
                                                    const name = bar?.payload?.name;
                                                    if (name) {
                                                        router.visit(ticketsHref(workspace, { category: name }));
                                                    }
                                                }}
                                            />
                                        </BarChart>
                                    </ChartContainer>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-xl font-semibold tracking-tight">Tickets by priority</CardTitle>
                                <CardDescription>New tickets by priority during the last 30 days.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {analytics.byPriorityInPeriod.length === 0 ? (
                                    <p className="text-muted-foreground py-16 text-center text-sm">No ticket activity in this reporting period.</p>
                                ) : (
                                    <ChartContainer config={distributionChartConfig} className="h-[250px] w-full">
                                        <BarChart
                                            accessibilityLayer
                                            data={analytics.byPriorityInPeriod}
                                            layout="vertical"
                                            margin={{ top: 4, right: 12, left: 10, bottom: 0 }}
                                        >
                                            <CartesianGrid horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                                            <YAxis
                                                dataKey="name"
                                                type="category"
                                                tickLine={false}
                                                axisLine={false}
                                                tickMargin={8}
                                                width={88}
                                                tickFormatter={(value) => PRIORITY_LABELS[value as Ticket['priority']] ?? value}
                                            />
                                            <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                                            <Bar
                                                dataKey="count"
                                                fill="var(--color-count)"
                                                radius={[0, 2, 2, 0]}
                                                maxBarSize={26}
                                                className="cursor-pointer"
                                                onClick={(bar) => {
                                                    const name = bar?.payload?.name;
                                                    if (name) {
                                                        router.visit(ticketsHref(workspace, { priority: name }));
                                                    }
                                                }}
                                            />
                                        </BarChart>
                                    </ChartContainer>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section aria-labelledby="agent-metrics-heading" className="flex flex-col gap-4">
                    <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                        <div>
                            <p className="stamp-label text-muted-foreground">Agent metrics</p>
                            <h2 id="agent-metrics-heading" className="font-display mt-1 text-2xl font-semibold tracking-tight">
                                Workload and service quality
                            </h2>
                        </div>
                        <p className="text-muted-foreground font-mono text-[11px]">Resolved activity: last 30 days</p>
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
                                        <Link
                                            key={row.name}
                                            href={ticketsHref(workspace, { status: row.name })}
                                            className="hover:bg-muted/50 flex items-center justify-between gap-3 rounded-sm px-1 py-1 text-sm"
                                        >
                                            <StatusBadge status={row.name as Ticket['status']} />
                                            <span className="font-mono font-medium tabular-nums">{row.count}</span>
                                        </Link>
                                    ))}
                                </div>
                                {analytics.byPriority.length > 0 && (
                                    <div className="border-t pt-4">
                                        <p className="stamp-label text-muted-foreground mb-3 text-[10px]">Priority</p>
                                        <div className="flex flex-col gap-3">
                                            {analytics.byPriority.map((row) => (
                                                <Link
                                                    key={row.name}
                                                    href={ticketsHref(workspace, { view: 'active', priority: row.name })}
                                                    className="hover:bg-muted/50 flex items-center justify-between gap-3 rounded-sm px-1 py-1 text-sm"
                                                >
                                                    <PriorityBadge priority={row.name as Ticket['priority']} />
                                                    <span className="font-mono font-medium tabular-nums">{row.count}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="shadow-none xl:col-span-2">
                            <CardHeader>
                                <CardTitle className="font-display text-xl font-semibold tracking-tight">Tickets assigned &amp; resolved</CardTitle>
                                <CardDescription>Current active assignments compared with resolved tickets in the reporting period.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {agentChartData.length === 0 ? (
                                    <p className="text-muted-foreground py-16 text-center text-sm">No support team members to report yet.</p>
                                ) : (
                                    <ChartContainer
                                        config={agentActivityChartConfig}
                                        className="w-full"
                                        style={{ height: Math.max(240, agentChartData.length * 46) }}
                                    >
                                        <BarChart
                                            accessibilityLayer
                                            data={agentChartData}
                                            layout="vertical"
                                            margin={{ top: 4, right: 12, left: 10, bottom: 0 }}
                                        >
                                            <CartesianGrid horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                                            <YAxis dataKey="name" type="category" tickLine={false} axisLine={false} tickMargin={8} width={112} />
                                            <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                                            <ChartLegend content={<ChartLegendContent />} />
                                            <Bar
                                                dataKey="ticketsAssigned"
                                                fill="var(--color-ticketsAssigned)"
                                                radius={[0, 2, 2, 0]}
                                                maxBarSize={18}
                                            />
                                            <Bar
                                                dataKey="ticketsResolved"
                                                fill="var(--color-ticketsResolved)"
                                                radius={[0, 2, 2, 0]}
                                                maxBarSize={18}
                                            />
                                        </BarChart>
                                    </ChartContainer>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="shadow-none">
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="font-display text-xl font-semibold tracking-tight">Agent service metrics</CardTitle>
                                <CardDescription className="mt-1">
                                    A detailed view of workload, responsiveness, SLA outcomes, and customer feedback.
                                </CardDescription>
                            </div>
                            <span className="stamp-label text-muted-foreground hidden text-[9px] sm:block">All agents</span>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[780px] text-left text-sm">
                                    <caption className="sr-only">Agent workload and support quality metrics</caption>
                                    <thead className="bg-muted/30 border-b">
                                        <tr>
                                            <th className="stamp-label text-muted-foreground px-3 py-3 text-[9px] font-medium">Agent</th>
                                            <th className="stamp-label text-muted-foreground px-3 py-3 text-right text-[9px] font-medium">
                                                Tickets assigned
                                            </th>
                                            <th className="stamp-label text-muted-foreground px-3 py-3 text-right text-[9px] font-medium">
                                                Tickets resolved
                                            </th>
                                            <th className="stamp-label text-muted-foreground px-3 py-3 text-right text-[9px] font-medium">
                                                Average response time
                                            </th>
                                            <th className="stamp-label text-muted-foreground px-3 py-3 text-right text-[9px] font-medium">
                                                SLA compliance
                                            </th>
                                            <th className="stamp-label text-muted-foreground px-3 py-3 text-right text-[9px] font-medium">
                                                Customer rating
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {analytics.agents.length === 0 && (
                                            <tr>
                                                <td colSpan={6} className="text-muted-foreground px-3 py-12 text-center">
                                                    No support team members to report yet.
                                                </td>
                                            </tr>
                                        )}
                                        {analytics.agents.map((agent) => (
                                            <tr key={agent.id} className="hover:bg-muted/30 border-b last:border-0">
                                                <td className="px-3 py-3">
                                                    <Link href={ticketsHref(workspace, { assignee: agent.id })} className="font-medium hover:underline">
                                                        {agent.name}
                                                    </Link>
                                                    <p className="text-muted-foreground mt-0.5 text-xs capitalize">{agent.role}</p>
                                                </td>
                                                <td className="px-3 py-3 text-right">
                                                    <p className="font-mono font-medium tabular-nums">{agent.ticketsAssigned}</p>
                                                    <p className="text-muted-foreground text-[10px]">active now</p>
                                                </td>
                                                <td className="px-3 py-3 text-right">
                                                    <p className="font-mono font-medium tabular-nums">{agent.ticketsResolved}</p>
                                                    <p className="text-muted-foreground text-[10px]">last 30 days</p>
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono font-medium tabular-nums">
                                                    {formatMinutes(agent.avgResponseMinutes)}
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono font-medium tabular-nums">
                                                    <Percentage value={agent.slaCompliance} />
                                                </td>
                                                <td className="px-3 py-3 text-right">
                                                    <p className="font-mono font-medium tabular-nums">
                                                        <Rating value={agent.customerRating} />
                                                    </p>
                                                    <p className="text-muted-foreground text-[10px]">
                                                        {agent.customerRatingResponses > 0
                                                            ? `${agent.customerRatingResponses} ratings`
                                                            : 'No ratings'}
                                                    </p>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="text-muted-foreground mt-4 text-xs leading-5">
                                Response and SLA results are attributed to a ticket&apos;s current assignee. Customer ratings will appear when
                                feedback collection is enabled.
                            </p>
                        </CardContent>
                    </Card>
                </section>

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
