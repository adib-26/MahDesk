import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/react';

export function Pagination<T>({ collection }: { collection: Paginated<T> }) {
    if (collection.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground text-sm">
                Page {collection.current_page} of {collection.last_page} · {collection.total} total
            </p>
            <div className="flex flex-wrap gap-1">
                {collection.links.map((link, index) => {
                    const label = link.label.replace(/&laquo;|&raquo;/g, '').trim();

                    if (!link.url) {
                        return (
                            <Button key={`${label}-${index}`} variant="outline" size="sm" disabled>
                                {label}
                            </Button>
                        );
                    }

                    return (
                        <Button key={`${label}-${index}`} variant={link.active ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={link.url} preserveScroll>
                                {label}
                            </Link>
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}
