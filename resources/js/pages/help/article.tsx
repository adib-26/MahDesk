import type { KbArticle } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props {
    workspace: { name: string; slug: string };
    article: KbArticle;
    related: KbArticle[];
}

export default function HelpArticle({ workspace, article, related }: Props) {
    return (
        <div className="bg-background min-h-screen">
            <Head title={article.title} />
            <header className="border-b">
                <div className="mx-auto flex max-w-3xl flex-col gap-2 p-6">
                    <Link href={route('help.index', workspace.slug)} className="text-muted-foreground text-sm underline">
                        {workspace.name} help center
                    </Link>
                    <p className="text-muted-foreground text-xs">{article.category?.name}</p>
                    <h1 className="text-3xl font-semibold">{article.title}</h1>
                </div>
            </header>
            <main className="mx-auto flex max-w-3xl flex-col gap-8 p-6">
                {article.excerpt && <p className="text-muted-foreground text-lg">{article.excerpt}</p>}
                <article className="whitespace-pre-wrap text-sm leading-7">{article.body}</article>
                {related.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-semibold">Related articles</h2>
                        {related.map((item) => (
                            <Link key={item.id} href={route('help.article', [workspace.slug, item.slug])} className="hover:bg-muted/40 rounded-lg border p-3">
                                <p className="font-medium">{item.title}</p>
                                <p className="text-muted-foreground text-sm">{item.excerpt}</p>
                            </Link>
                        ))}
                    </section>
                )}
            </main>
        </div>
    );
}
