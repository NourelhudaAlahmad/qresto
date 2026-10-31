import { Head } from '@inertiajs/react';

type Props = {
    title: string;
};

export default function StaffLanding({ title }: Props) {
    return (
        <>
            <Head title={title} />

            <section className="rounded-xl border bg-white p-6">
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="text-body mt-2 text-gray-500">
                    This staff panel is coming soon.
                </p>
            </section>
        </>
    );
}
