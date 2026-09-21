import type { KbArticle } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props {
    workspace: { name: string; slug: string };
    article: KbArticle;
    related: KbArticle[];
}

export default function HelpArticle({ workspace, article, related }: Props) {
    return (
        <div className="paper-grain bg-background min-h-screen">
            <Head title={article.title} />
            <header className="border-b">
                <div className="mx-auto flex max-w-3xl flex-col gap-2 p-6">
                    <Link
                        href={route('help.index', workspace.slug)}
                        className="text-muted-foreground font-mono text-xs tracking-wide underline-offset-4 hover:underline"
                    >
                        ← {workspace.name} help center
                    </Link>
                    <p className="stamp-label text-stamp mt-3">{article.category?.name}</p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight text-balance">{article.title}</h1>
                </div>
            </header>
            <main className="mx-auto flex max-w-3xl flex-col gap-8 p-6">
                {article.excerpt && <p className="text-muted-foreground font-display text-xl leading-relaxed italic">{article.excerpt}</p>}
                <article className="text-[15px] leading-7 whitespace-pre-wrap">{article.body}</article>
                {related.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-display text-lg font-semibold tracking-tight">Related articles</h2>
                        <div className="rule-double" />
                        <div className="divide-border bg-card divide-y rounded-sm border">
                            {related.map((item) => (
                                <Link key={item.id} href={route('help.article', [workspace.slug, item.slug])} className="hover:bg-muted/40 block p-4">
                                    <p className="font-medium">{item.title}</p>
                                    <p className="text-muted-foreground text-sm">{item.excerpt}</p>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </div>
    );
}
