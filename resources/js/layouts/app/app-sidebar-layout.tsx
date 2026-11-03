import { usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert } from 'lucide-react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { flash } = usePage().props as {
        flash?: { success?: string | null; error?: string | null };
    };

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {(flash?.success || flash?.error) && (
                    <div className="px-4 pt-4 sm:px-6 lg:px-8">
                        <div
                            className={`mx-auto flex w-full max-w-[1600px] items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm ${flash.error ? 'border-red-500/25 bg-red-500/10 text-red-700 dark:text-red-300' : 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'}`}
                            role="status"
                        >
                            {flash.error ? (
                                <CircleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                            ) : (
                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                            )}
                            <span className="font-medium">
                                {flash.error || flash.success}
                            </span>
                        </div>
                    </div>
                )}
                {children}
            </AppContent>
        </AppShell>
    );
}
