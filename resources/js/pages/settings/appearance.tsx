import { Head, setLayoutProps } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { useI18n } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const {
        staff: {
            account: { appearance: t },
        },
    } = useI18n();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t.head,
                href: editAppearance(),
            },
        ],
    });

    return (
        <>
            <Head title={t.head} />

            <h1 className="sr-only">{t.head}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t.head}
                    description={t.description}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
