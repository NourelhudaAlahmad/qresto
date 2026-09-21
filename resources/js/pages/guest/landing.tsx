import { Head, router, usePage } from '@inertiajs/react';
import { QrCode } from 'lucide-react';
import { ArrowIcon } from '@/components/qresto/arrow-icon';
import { Button } from '@/components/qresto/button';
import { Card } from '@/components/qresto/card';
import { Divider } from '@/components/qresto/divider';
import { PhoneIcon } from '@/components/qresto/phone-icon';
import GuestLayout from '@/layouts/guest-layout';
import type { LandingPageProps, LandingSharedProps } from '@/types/landing';

function formatTime(time: string): string {
    const [hourText, minuteText] = time.split(':');
    const hour = Number(hourText);

    const suffix = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;

    return `${displayHour}:${minuteText} ${suffix}`;
}

export default function Landing({
    restaurant,
    hours,
    featured_items,
    open_now,
    translations,
}: LandingPageProps) {
    const { locale = 'en', flash } = usePage<LandingSharedProps>().props;

    const nextLocale = locale === 'en' ? 'ar' : 'en';
    const nextLanguageLabel = nextLocale === 'ar' ? 'العربية' : 'English';

    const switchLocale = () => {
        router.post(
            '/locale',
            {
                locale: nextLocale,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const switchLanguageLabel = translations.switch_language.replace(
        ':language',
        nextLanguageLabel,
    );

    const bookingHref = restaurant.phone
        ? `tel:${restaurant.phone.replace(/\s+/g, '')}`
        : '#book';

    return (
        <GuestLayout>
            <Head title={restaurant.name} />

            {flash?.error && (
                <div role="alert" className="mx-auto max-w-[390px] px-3 pt-3">
                    <div className="rounded-[var(--radius-container)] border border-[var(--danger-border)] bg-[var(--danger-bg)] px-4 py-3 text-sm text-[var(--danger-fg)]">
                        {flash.error}
                    </div>
                </div>
            )}

            <header className="border-border-subtle bg-surface-page/88 sticky top-0 z-30 h-14 border-b px-3 backdrop-blur-xl">
                <div className="mx-auto flex h-full max-w-[390px] items-center justify-between gap-3">
                    <div className="font-ui min-w-0 truncate text-base font-semibold tracking-tight">
                        QResto
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <span
                            className={[
                                'rounded-pill px-2.5 py-1 text-xs font-medium',
                                open_now
                                    ? 'bg-[var(--success-bg)] text-[var(--success-fg)]'
                                    : 'bg-surface-muted text-text-secondary',
                            ].join(' ')}
                        >
                            {open_now
                                ? translations.open_now
                                : translations.closed}
                        </span>

                        <button
                            type="button"
                            onClick={switchLocale}
                            className="rounded-pill border-border-default hover:bg-action-ghost-hover focus-visible:ring-border-focus-color/30 min-h-11 border px-3 py-1 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2"
                            aria-label={switchLanguageLabel}
                        >
                            {nextLanguageLabel}
                        </button>
                    </div>
                </div>
            </header>

            <main>
                <section className="px-3 pt-3">
                    <div className="relative h-[420px] overflow-hidden rounded-[24px] bg-gradient-to-br from-stone-900 via-stone-700 to-amber-900">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(255,255,255,0.16),transparent_28%),radial-gradient(circle_at_80%_70%,rgba(255,180,90,0.18),transparent_32%)]" />

                        <div className="absolute inset-x-0 bottom-0 bg-[var(--scrim-bottom)] p-6 pt-32 text-white">
                            {restaurant.cuisine && (
                                <p className="font-ui mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/70">
                                    {restaurant.cuisine}
                                </p>
                            )}

                            <h1 className="font-display max-w-[17ch] text-[44px] font-semibold leading-[1.05] tracking-[-0.03em]">
                                {restaurant.tagline ||
                                    translations.hero_fallback}
                            </h1>

                            {restaurant.description && (
                                <p className="mt-4 max-w-[34ch] text-[15px] leading-[1.45] text-white/80">
                                    {restaurant.description}
                                </p>
                            )}
                        </div>
                    </div>
                </section>

                <section className="px-3 py-5">
                    <div className="space-y-3">
                        <Button
                            asChild
                            size="lg"
                            variant="primary"
                            fullWidth
                            className="text-text-on-brand justify-between"
                        >
                            <a href="#scan">
                                <span>{translations.scan}</span>
                                <QrCode aria-hidden="true" className="size-5" />
                            </a>
                        </Button>

                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            fullWidth
                            className="justify-between"
                        >
                            <a href={bookingHref}>
                                <span>{translations.book}</span>
                                <ArrowIcon />
                            </a>
                        </Button>
                    </div>

                    <p className="text-text-secondary mt-3 text-center text-xs leading-5">
                        {translations.reassurance}
                    </p>
                </section>

                <section className="pb-8 pt-2">
                    <div className="px-3">
                        <div className="mb-4">
                            <p className="font-ui text-text-secondary text-[11px] font-semibold uppercase tracking-[0.12em]">
                                {translations.kitchen_eyebrow}
                            </p>

                            <h2 className="font-display mt-1 text-[34px] font-semibold leading-tight tracking-[-0.03em]">
                                {translations.kitchen_title}
                            </h2>
                        </div>
                    </div>

                    <div className="flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        {featured_items.map((item) => (
                            <Card
                                key={item.id}
                                className="w-[230px] shrink-0 snap-start overflow-hidden p-0 sm:p-0"
                            >
                                <div className="h-[128px] bg-gradient-to-br from-stone-800 via-stone-600 to-amber-700">
                                    <div className="flex h-full items-end p-4">
                                        {item.chef_flag && (
                                            <span className="rounded-pill bg-surface-card/90 font-ui text-text-primary px-2.5 py-1 text-[11px] font-semibold shadow-sm backdrop-blur-sm">
                                                {translations.chef_pick}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-display text-[20px] font-semibold leading-snug tracking-[-0.015em]">
                                            {item.name}
                                        </h3>

                                        <span
                                            dir="ltr"
                                            className="shrink-0 font-mono text-sm font-semibold tabular-nums"
                                        >
                                            {item.price}
                                        </span>
                                    </div>
                                </div>
                            </Card>
                        ))}

                        {featured_items.length === 0 && (
                            <Card className="text-text-secondary border-dashed text-sm">
                                {translations.no_featured_items}
                            </Card>
                        )}
                    </div>
                </section>

                <section className="px-3 py-8">
                    <Divider />

                    <div className="pt-8">
                        <p className="font-ui text-text-secondary text-[11px] font-semibold uppercase tracking-[0.12em]">
                            {translations.room}
                        </p>

                        <p className="text-text-secondary mt-3 text-[17px] leading-[1.65]">
                            {restaurant.description ||
                                translations.room_fallback}
                        </p>

                        <div className="mt-7">
                            {hours.map((hour) => (
                                <div key={hour.day_of_week}>
                                    <div
                                        className={[
                                            'flex items-center justify-between py-3 font-mono text-sm tabular-nums',
                                            hour.is_today
                                                ? 'text-text-primary font-semibold'
                                                : 'text-text-secondary',
                                        ].join(' ')}
                                    >
                                        <span className="font-sans text-sm">
                                            {translations.days[hour.day] ??
                                                hour.day}
                                        </span>

                                        <span dir="ltr">
                                            {hour.opens_at && hour.closes_at
                                                ? `${formatTime(hour.opens_at)} – ${formatTime(hour.closes_at)}`
                                                : translations.closed}
                                        </span>
                                    </div>

                                    <Divider />
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="px-3 py-4">
                    <Card className="p-5 sm:p-5">
                        <p className="font-ui text-text-secondary text-[11px] font-semibold uppercase tracking-[0.12em]">
                            {translations.find_us}
                        </p>

                        <div className="mt-4 overflow-hidden rounded-[var(--radius-lg)] bg-gradient-to-br from-stone-200 via-stone-100 to-amber-100">
                            <div className="flex h-[180px] items-end p-4">
                                <div className="bg-surface-card/85 rounded-[var(--radius-md)] px-3 py-2 text-xs font-medium shadow-sm backdrop-blur-sm">
                                    {restaurant.name}
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            {restaurant.address && (
                                <p className="text-text-secondary text-sm leading-6">
                                    {restaurant.address}
                                </p>
                            )}

                            {restaurant.phone && (
                                <a
                                    href={`tel:${restaurant.phone.replace(/\s+/g, '')}`}
                                    className="inline-flex min-h-11 items-center gap-2 text-sm font-medium"
                                >
                                    <PhoneIcon />

                                    <span dir="ltr">{restaurant.phone}</span>
                                </a>
                            )}
                        </div>
                    </Card>
                </section>
            </main>

            <footer className="px-3 pb-8 pt-10">
                <Divider />

                <div className="pt-6 text-center">
                    <div className="font-ui text-sm font-semibold tracking-tight">
                        QResto
                    </div>

                    <p className="text-text-secondary mt-2 text-xs">
                        {translations.powered_by}
                    </p>
                </div>
            </footer>
        </GuestLayout>
    );
}
