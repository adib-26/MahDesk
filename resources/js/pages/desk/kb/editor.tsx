import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { KbArticle, SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    article: KbArticle | null;
    categories: { id: number; name: string }[];
}

export default function KnowledgeBaseEditor({ article, categories }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({
        title: article?.title ?? '',
        kb_category_id: article?.kb_category_id ? String(article.kb_category_id) : categories[0] ? String(categories[0].id) : '',
        excerpt: article?.excerpt ?? '',
        body: article?.body ?? '',
        status: article?.status ?? 'draft',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (article) {
            form.patch(deskRoute('desk.kb.update', workspace, { article: article.id }));
        } else {
            form.post(deskRoute('desk.kb.store', workspace));
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Knowledge base', href: deskRoute('desk.kb.index', workspace) },
                { title: article ? article.title : 'New article', href: article ? deskRoute('desk.kb.edit', workspace, { article: article.id }) : deskRoute('desk.kb.create', workspace) },
            ]}
        >
            <Head title={article ? `Edit ${article.title}` : 'New article'} />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{article ? 'Edit article' : 'New article'}</h1>
                    {article && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (confirm('Delete this article?')) {
                                    router.delete(deskRoute('desk.kb.destroy', workspace, { article: article.id }));
                                }
                            }}
                        >
                            Delete
                        </Button>
                    )}
                </div>
                {categories.length === 0 && <p className="text-muted-foreground text-sm">Create a category before publishing articles.</p>}
                <div className="grid gap-2">
                    <Label>Title</Label>
                    <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label>Category</Label>
                        <Select value={form.data.kb_category_id} onValueChange={(value) => form.setData('kb_category_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.kb_category_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Status</Label>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value as 'draft' | 'published')}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="published">Published</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label>Excerpt</Label>
                    <Input value={form.data.excerpt} onChange={(event) => form.setData('excerpt', event.target.value)} />
                </div>
                <div className="grid gap-2">
                    <Label>Body</Label>
                    <Textarea className="min-h-[320px]" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} required />
                    <InputError message={form.errors.body} />
                </div>
                <Button type="submit" disabled={form.processing || categories.length === 0}>
                    {article ? 'Save article' : 'Create article'}
                </Button>
            </form>
        </AppLayout>
    );
}
