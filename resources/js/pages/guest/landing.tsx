import { Head, router, usePage } from '@inertiajs/react';
import type { Html5Qrcode } from 'html5-qrcode';
import { QrCode, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

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

function extractQrToken(value: string): string | null {
    const scannedValue = value.trim();

    if (scannedValue === '') {
        return null;
    }

    try {
        const url = new URL(scannedValue, window.location.origin);

        const match = url.pathname.match(/^\/t\/([^/]+)\/?$/);

        if (match?.[1]) {
            return decodeURIComponent(match[1]);
        }
    } catch {
        // The scanned value may be a raw token rather than a URL.
    }

    if (/^[A-Za-z0-9_-]+$/.test(scannedValue)) {
        return scannedValue;
    }

    return null;
}

async function stopScanner(scanner: Html5Qrcode): Promise<void> {
    try {
        await scanner.stop();
    } catch {
        // The scanner may not have finished starting yet.
    }

    try {
        scanner.clear();
    } catch {
        // The scanner UI may already have been cleared.
    }
}

export default function Landing({
    restaurant,
    hours,
    featured_items,
    open_now,
    translations,
}: LandingPageProps) {
    const { locale = 'en', flash } = usePage<LandingSharedProps>().props;

    const [scannerOpen, setScannerOpen] = useState(false);
    const [bookingOpen, setBookingOpen] = useState(false);
    const [scannerError, setScannerError] = useState<string | null>(null);

    const scannerInstanceRef = useRef<Html5Qrcode | null>(null);

    const nextLocale = locale === 'en' ? 'ar' : 'en';
    const nextLanguageLabel = nextLocale === 'ar' ? 'العربية' : 'English';

    const scannerCopy =
        locale === 'ar'
            ? {
                  title: 'امسح رمز الطاولة',
                  description: 'وجّه الكاميرا نحو رمز QR الموجود على طاولتك.',
                  close: 'إغلاق الماسح',
                  invalid: 'هذا الرمز ليس رمز طاولة صالحًا في QResto.',
                  cameraError:
                      'تعذر فتح الكاميرا. اسمح بالوصول إلى الكاميرا وحاول مرة أخرى.',
              }
            : {
                  title: 'Scan your table code',
                  description:
                      'Point your camera at the QR code on your table.',
                  close: 'Close scanner',
                  invalid: 'This is not a valid QResto table code.',
                  cameraError:
                      'The camera could not be opened. Allow camera access and try again.',
              };

    const bookingCopy =
        locale === 'ar'
            ? {
                  title: 'احجز طاولة',
                  description:
                      'للحجز، تواصل مع المطعم مباشرة على الرقم التالي.',
                  call: 'اتصل الآن',
                  close: 'إغلاق نافذة الحجز',
                  unavailable: 'رقم الهاتف غير متوفر حاليًا.',
              }
            : {
                  title: 'Book a table',
                  description:
                      'To make a reservation, contact the restaurant directly.',
                  call: 'Call now',
                  close: 'Close booking dialog',
                  unavailable: 'The phone number is currently unavailable.',
              };

    useEffect(() => {
        if (!scannerOpen) {
            return;
        }

        let disposed = false;

        const startScanner = async () => {
            try {
                setScannerError(null);

                const { Html5Qrcode } = await import('html5-qrcode');

                if (disposed) {
                    return;
                }

                const scanner = new Html5Qrcode('landing-qr-reader');

                scannerInstanceRef.current = scanner;

                await scanner.start(
                    {
                        facingMode: 'environment',
                    },
                    {
                        fps: 10,
                        qrbox: {
                            width: 240,
                            height: 240,
                        },
                    },
                    (decodedText) => {
                        const qrToken = extractQrToken(decodedText);

                        if (qrToken === null) {
                            setScannerError(scannerCopy.invalid);

                            return;
                        }

                        scannerInstanceRef.current = null;

                        void stopScanner(scanner).finally(() => {
                            router.visit(`/t/${encodeURIComponent(qrToken)}`);
                        });
                    },
                    () => undefined,
                );

                if (disposed) {
                    scannerInstanceRef.current = null;

                    await stopScanner(scanner);
                }
            } catch {
                if (!disposed) {
                    scannerInstanceRef.current = null;
                    setScannerError(scannerCopy.cameraError);
                }
            }
        };

        void startScanner();

        return () => {
            disposed = true;

            const scanner = scannerInstanceRef.current;

            if (scanner === null) {
                return;
            }

            scannerInstanceRef.current = null;

            void stopScanner(scanner);
        };
    }, [scannerCopy.cameraError, scannerCopy.invalid, scannerOpen]);

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

            {scannerOpen && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="landing-scanner-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                >
                    <div className="bg-surface-card relative w-full max-w-[390px] rounded-[var(--radius-container)] p-4 shadow-xl">
                        <button
                            type="button"
                            onClick={() => setScannerOpen(false)}
                            aria-label={scannerCopy.close}
                            className="hover:bg-action-ghost-hover focus-visible:ring-border-focus-color/30 absolute end-3 top-3 z-10 flex size-11 items-center justify-center rounded-full transition focus-visible:outline-none focus-visible:ring-2"
                        >
                            <X aria-hidden="true" className="size-5" />
                        </button>

                        <div className="pe-12">
                            <h2
                                id="landing-scanner-title"
                                className="font-display text-[26px] font-semibold leading-tight tracking-[-0.02em]"
                            >
                                {scannerCopy.title}
                            </h2>

                            <p className="text-text-secondary mt-2 text-sm leading-6">
                                {scannerCopy.description}
                            </p>
                        </div>

                        <div className="mt-5 overflow-hidden rounded-[var(--radius-lg)] bg-black">
                            <div
                                id="landing-qr-reader"
                                className="min-h-[300px] w-full"
                            />
                        </div>

                        {scannerError && (
                            <div
                                role="alert"
                                className="mt-4 rounded-[var(--radius-md)] border border-[var(--danger-border)] bg-[var(--danger-bg)] px-4 py-3 text-sm text-[var(--danger-fg)]"
                            >
                                {scannerError}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {bookingOpen && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="landing-booking-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                >
                    <div className="bg-surface-card relative w-full max-w-[390px] rounded-[var(--radius-container)] p-5 shadow-xl">
                        <button
                            type="button"
                            onClick={() => setBookingOpen(false)}
                            aria-label={bookingCopy.close}
                            className="hover:bg-action-ghost-hover focus-visible:ring-border-focus-color/30 absolute end-3 top-3 flex size-11 items-center justify-center rounded-full transition focus-visible:outline-none focus-visible:ring-2"
                        >
                            <X aria-hidden="true" className="size-5" />
                        </button>

                        <div className="pe-12">
                            <h2
                                id="landing-booking-title"
                                className="font-display text-[26px] font-semibold leading-tight tracking-[-0.02em]"
                            >
                                {bookingCopy.title}
                            </h2>

                            <p className="text-text-secondary mt-2 text-sm leading-6">
                                {bookingCopy.description}
                            </p>
                        </div>

                        <div className="mt-6">
                            {restaurant.phone ? (
                                <>
                                    <div
                                        dir="ltr"
                                        className="bg-surface-muted mb-4 rounded-[var(--radius-md)] px-4 py-3 text-center font-mono text-base font-semibold"
                                    >
                                        {restaurant.phone}
                                    </div>

                                    <Button
                                        asChild
                                        size="lg"
                                        variant="primary"
                                        fullWidth
                                    >
                                        <a
                                            href={`tel:${restaurant.phone.replace(/\s+/g, '')}`}
                                        >
                                            <span>{bookingCopy.call}</span>

                                            <PhoneIcon />
                                        </a>
                                    </Button>
                                </>
                            ) : (
                                <p className="text-text-secondary text-sm">
                                    {bookingCopy.unavailable}
                                </p>
                            )}
                        </div>
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
                            type="button"
                            size="lg"
                            variant="primary"
                            fullWidth
                            onClick={() => setScannerOpen(true)}
                            className="text-text-on-brand justify-between"
                        >
                            <span>{translations.scan}</span>

                            <QrCode aria-hidden="true" className="size-5" />
                        </Button>

                        <Button
                            type="button"
                            size="lg"
                            variant="outline"
                            fullWidth
                            onClick={() => setBookingOpen(true)}
                            className="justify-between"
                        >
                            <span>{translations.book}</span>

                            <ArrowIcon />
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
