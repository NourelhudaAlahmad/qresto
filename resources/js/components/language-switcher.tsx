import { router, usePage } from '@inertiajs/react';

type PageProps = {
    locale: 'en' | 'ar';
    available_locales: string[];
};

const labels = {
    en: 'English',
    ar: 'العربية',
};

export function LanguageSwitcher() {
    const { locale, available_locales } = usePage<PageProps>().props;

    function switchLocale(nextLocale: string) {
        if (nextLocale === locale) {
            return;
        }

        router.post(
            '/locale',
            {
                locale: nextLocale,
            },
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <div
            className="flex items-center gap-1 rounded-md border p-1"
            aria-label="Language"
        >
            {available_locales.map((availableLocale) => (
                <button
                    key={availableLocale}
                    type="button"
                    onClick={() => switchLocale(availableLocale)}
                    disabled={availableLocale === locale}
                    aria-pressed={availableLocale === locale}
                    className="rounded-sm px-2 py-1 text-xs font-medium transition disabled:cursor-default disabled:opacity-100"
                >
                    {labels[availableLocale as keyof typeof labels] ??
                        availableLocale}
                </button>
            ))}
        </div>
    );
}
