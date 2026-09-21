import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
type PageProps = {
    locale: 'en' | 'ar';
    dir: 'ltr' | 'rtl';
};

export function LocaleSync() {
    const { locale, dir } = usePage<PageProps>().props;

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [locale, dir]);

    return null;
}
