import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
type PageProps = {
    locale: 'en' | 'ar';
    dir: 'ltr' | 'rtl';
};

function applyDocumentLocale(props: Partial<PageProps>): void {
    if (props.locale) {
        document.documentElement.lang = props.locale;
    }

    if (props.dir) {
        document.documentElement.dir = props.dir;
    }
}

export function LocaleSync() {
    const { locale, dir } = usePage<PageProps>().props;

    useEffect(() => {
        applyDocumentLocale({ locale, dir });
    }, [locale, dir]);

    return null;
}

/**
 * The server sets <html lang dir> on the first load only. Every page
 * visit after that (switching language on staff sign-in or in settings,
 * where no layout renders <LocaleSync>) must carry it over, or Arabic copy
 * sits in a left-to-right page and money and dates format in English.
 */
export function syncDocumentLocaleOnNavigate(): () => void {
    return router.on('navigate', (event) => {
        applyDocumentLocale(
            (event.detail.page.props ?? {}) as Partial<PageProps>,
        );
    });
}
