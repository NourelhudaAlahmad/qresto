import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/qresto/button';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { showToast } from '@/components/qresto/toast';
import { useI18n } from '@/lib/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    restaurantPhone?: string | null;
    onCallWaiter: () => void;
};

export function HelpPanel({
    open,
    onOpenChange,
    restaurantPhone,
    onCallWaiter,
}: Props) {
    const { guest: g } = useI18n();
    const [changingLanguage, setChangingLanguage] = useState(false);

    function changeLanguage(locale: 'en' | 'ar') {
        if (changingLanguage) {
            return;
        }

        setChangingLanguage(true);

        router.post(
            '/locale',
            { locale },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: () =>
                    showToast({
                        type: 'error',
                        title: g.help.language_failed,
                        description: g.help.try_again,
                    }),
                onFinish: () => setChangingLanguage(false),
            },
        );
    }

    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={onOpenChange}
            title={g.help.title}
        >
            <div className="space-y-5">
                <section className="space-y-2">
                    <h3 className="font-semibold">{g.help.allergens_title}</h3>
                    <p className="text-body text-[var(--text-secondary)]">
                        {g.help.allergens_body}
                    </p>
                </section>

                <section className="space-y-2">
                    <h3 className="font-semibold">{g.help.contact_title}</h3>

                    {restaurantPhone ? (
                        <a
                            href={`tel:${restaurantPhone}`}
                            className="text-body inline-block font-medium underline"
                        >
                            {restaurantPhone}
                        </a>
                    ) : (
                        <p className="text-body text-[var(--text-secondary)]">
                            {g.help.no_phone}
                        </p>
                    )}
                </section>

                <section className="space-y-2">
                    <h3 className="font-semibold">{g.help.language}</h3>

                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={changingLanguage}
                            onClick={() => changeLanguage('en')}
                        >
                            English
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            disabled={changingLanguage}
                            onClick={() => changeLanguage('ar')}
                        >
                            العربية
                        </Button>
                    </div>
                </section>

                <section className="border-t border-[var(--border-subtle)] pt-4">
                    <Button
                        type="button"
                        fullWidth
                        onClick={() => {
                            onOpenChange(false);
                            onCallWaiter();
                        }}
                    >
                        {g.help.call_waiter}
                    </Button>
                </section>
            </div>
        </ResponsiveOverlay>
    );
}
