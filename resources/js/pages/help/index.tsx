import { Input } from '@/components/ui/input';
import type { KbCategory } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Props {
    workspace: { name: string; slug: string };
    categories: KbCategory[];
    filters: { q?: string };
}

export default function HelpIndex({ workspace, categories, filters }: Props) {
    return (
        <div className="bg-background min-h-screen">
            <Head title={`${workspace.name} Help Center`} />
            <header className="border-b">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 p-6">
                    <div>
                        <p className="text-muted-foreground text-xs tracking-wide uppercase">Help Center</p>
                        <h1 className="text-2xl font-semibold">{workspace.name}</h1>
                    </div>
                    <Link href={route('home')} className="text-sm underline">
                        Powered by Desk
                    </Link>
                </div>
            </header>
            <main className="mx-auto flex max-w-4xl flex-col gap-6 p-6">
                <Input
                    placeholder="Search articles"
                    defaultValue={filters.q}
                    onKeyDown={(event) =>
                        event.key === 'Enter' &&
                        router.get(route('help.index', workspace.slug), { q: event.currentTarget.value }, { preserveState: true, replace: true })
                    }
                />
                {categories.length === 0 && <p className="text-muted-foreground">No published articles yet.</p>}
                {categories.map((category) => (
                    <section key={category.id} className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold">{category.name}</h2>
                        {category.articles?.map((article) => (
                            <Link
                                key={article.id}
                                href={route('help.article', [workspace.slug, article.slug])}
                                className="hover:bg-muted/40 rounded-xl border p-4"
                            >
                                <p className="font-medium">{article.title}</p>
                                <p className="text-muted-foreground text-sm">{article.excerpt}</p>
                            </Link>
                        ))}
                    </section>
                ))}
            </main>
        </div>
    );
}
