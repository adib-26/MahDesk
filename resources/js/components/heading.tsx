export default function Heading({ title, description }: { title: string; description?: string }) {
    return (
        <>
            <div className="mb-8 space-y-1">
                <h2 className="font-display text-2xl font-semibold tracking-tight">{title}</h2>
                {description && <p className="text-muted-foreground text-sm">{description}</p>}
            </div>
        </>
    );
}
