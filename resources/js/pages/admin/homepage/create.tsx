import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, LayoutTemplate, Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import adminRoutes from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminRoutes.dashboard().url },
    { title: 'Homepage Configs', href: adminRoutes.homepage.index().url },
    { title: 'New Client', href: adminRoutes.homepage.create().url },
];

export default function HomepageCreate() {
    const { data, setData, post, processing, errors } = useForm({
        label: '',
        client_key: '',
        is_active: true,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(adminRoutes.homepage.store().url);
    }

    function deriveKey(label: string) {
        return label
            .toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9\-_]/g, '');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Homepage Config — Admin" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="mb-4 -ml-3"
                        onClick={() => history.back()}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to configurations
                    </Button>
                    <div className="flex items-start gap-4">
                        <span className="rounded-2xl bg-blue-500/10 p-3 text-blue-600 dark:text-blue-400">
                            <LayoutTemplate className="h-6 w-6" />
                        </span>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Create homepage configuration
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Set up a new storefront workspace and start
                                adding sections.
                            </p>
                        </div>
                    </div>
                </div>
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-2xl border bg-card p-6 shadow-sm sm:p-8"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="label">Label</Label>
                        <Input
                            id="label"
                            placeholder="e.g. Website"
                            value={data.label}
                            onChange={(e) => {
                                setData('label', e.target.value);

                                if (
                                    !data.client_key ||
                                    data.client_key === deriveKey(data.label)
                                ) {
                                    setData(
                                        'client_key',
                                        deriveKey(e.target.value),
                                    );
                                }
                            }}
                            required
                        />
                        <InputError message={errors.label} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="client_key">Client Key</Label>
                        <Input
                            id="client_key"
                            placeholder="e.g. website or mobile-app"
                            value={data.client_key}
                            onChange={(e) =>
                                setData('client_key', e.target.value)
                            }
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            Lowercase letters, numbers, hyphens and underscores
                            only. Used in API calls:{' '}
                            <code className="rounded bg-muted px-1">
                                GET /api/homepage?client=website
                            </code>
                        </p>
                        <InputError message={errors.client_key} />
                    </div>

                    <div className="flex items-center justify-between rounded-xl border bg-muted/30 p-4">
                        <div>
                            <Label
                                htmlFor="is_active"
                                className="font-semibold"
                            >
                                Publish configuration
                            </Label>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Make this workspace available through the
                                homepage API.
                            </p>
                        </div>
                        <Switch
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(v) => setData('is_active', v)}
                        />
                    </div>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            <Plus className="mr-2 h-4 w-4" />
                            {processing ? 'Creating…' : 'Create configuration'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => history.back()}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
