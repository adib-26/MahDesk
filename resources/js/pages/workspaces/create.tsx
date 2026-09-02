import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function CreateWorkspace() {
    const { data, setData, post, processing, errors } = useForm({ name: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('workspaces.store'));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'New workspace', href: route('workspaces.create') }]}>
            <Head title="Create workspace" />
            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Create a workspace</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Each company gets its own Desk workspace — tickets, customers, agents, and knowledge stay isolated.
                    </p>
                </div>
                <form onSubmit={submit} className="flex flex-col gap-4 rounded-xl border p-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Company or workspace name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            placeholder="Northwind Support"
                            autoFocus
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create workspace
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
