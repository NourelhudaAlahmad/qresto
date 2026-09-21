import { Breadcrumbs } from '@/components/breadcrumbs';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="border-sidebar-border/50 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear md:px-4">
            {' '}
            <div className="flex items-center gap-2">
                {' '}
                <SidebarTrigger className="-ms-1" />{' '}
                <Breadcrumbs breadcrumbs={breadcrumbs} />{' '}
            </div>
            <div className="ms-auto">
                <LanguageSwitcher />
            </div>
        </header>
    );
}
