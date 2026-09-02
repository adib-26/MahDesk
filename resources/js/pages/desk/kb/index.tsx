import { Badge } from '@/components/ui/badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { deskRoute, relativeTime } from '@/lib/desk';
import type { KbCategory, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    categories: KbCategory[];
    filters: { q?: string };
}

export default function KnowledgeBaseIndex({ categories, filters }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', description: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(deskRoute('desk.kb.categories.store', workspace), {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Knowledge base', href: deskRoute('desk.kb.index', workspace) }]}>
            <Head title="Knowledge base" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Knowledge base</h1>
                        <p className="text-muted-foreground text-sm">Write help articles and publish them to your public help center.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={route('help.index', workspace.slug)} target="_blank" rel="noreferrer">
                                View help center
                            </a>
                        </Button>
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline">New category</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>New category</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={submit} className="flex flex-col gap-3">
                                    <div className="grid gap-2">
                                        <Label>Name</Label>
                                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Description</Label>
                                        <Input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                                    </div>
                                    <Button type="submit" disabled={form.processing}>
                                        Create category
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Button asChild>
                            <Link href={deskRoute('desk.kb.create', workspace)}>
                                <Plus /> New article
                            </Link>
                        </Button>
                    </div>
                </div>

                <Input
                    placeholder="Search articles"
                    defaultValue={filters.q}
                    onKeyDown={(event) =>
                        event.key === 'Enter' &&
                        router.get(deskRoute('desk.kb.index', workspace), { q: event.currentTarget.value }, { preserveState: true, replace: true })
                    }
                />

                {categories.length === 0 && <p className="text-muted-foreground">Create a category to start writing articles.</p>}

                <div className="grid gap-4">
                    {categories.map((category) => (
                        <Card key={category.id}>
                            <CardHeader className="flex-row items-start justify-between gap-3">
                                <div>
                                    <CardTitle>{category.name}</CardTitle>
                                    <CardDescription>{category.description || `${category.articles?.length ?? 0} articles`}</CardDescription>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        if (confirm('Delete this category and its articles?')) {
                                            router.delete(deskRoute('desk.kb.categories.destroy', workspace, { category: category.id }));
                                        }
                                    }}
                                >
                                    Delete
                                </Button>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {(category.articles ?? []).length === 0 && <p className="text-muted-foreground text-sm">No articles in this category.</p>}
                                {category.articles?.map((article) => (
                                    <Link
                                        key={article.id}
                                        href={deskRoute('desk.kb.edit', workspace, { article: article.id })}
                                        className="hover:bg-muted/50 flex items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="font-medium">{article.title}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {article.excerpt || 'No excerpt'} · {article.views} views · {relativeTime(article.updated_at)}
                                            </p>
                                        </div>
                                        <Badge variant={article.status === 'published' ? 'default' : 'secondary'}>{article.status}</Badge>
                                    </Link>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
