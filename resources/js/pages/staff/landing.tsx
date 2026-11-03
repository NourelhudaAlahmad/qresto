import { Head } from '@inertiajs/react';

import { useI18n } from '@/lib/i18n';

type Props = {
    title: string;
};

export default function StaffLanding({ title }: Props) {
    const {
        staff: { landing: t },
    } = useI18n();

    return (
        <>
            <Head title={title} />

            <section className="rounded-xl border bg-white p-6">
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="text-body mt-2 text-gray-500">{t.coming_soon}</p>
            </section>
        </>
    );
}
