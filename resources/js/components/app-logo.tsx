import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate font-semibold leading-tight">Central Smoke Distro</span>
                <span className="truncate text-[10px] font-medium uppercase tracking-[0.16em] text-muted-foreground">Content Admin</span>
            </div>
        </>
    );
}
