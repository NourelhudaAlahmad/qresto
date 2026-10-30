import { Head, router, usePage } from '@inertiajs/react';
import type { Html5Qrcode } from 'html5-qrcode';
import { MapPin, Phone, QrCode, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Numeric } from '@/components/numeric';
import { Button } from '@/components/qresto/button';
import { PhoneIcon } from '@/components/qresto/phone-icon';
import GuestLayout from '@/layouts/guest-layout';
import type {
    LandingPageProps,
    LandingSharedProps,
    OpeningHour,
} from '@/types/landing';

/** "17:00:00" → "17:00": the design shows opening hours on a 24-hour clock. */
function clock(time: string): string {
    return time.slice(0, 5);
}

type HoursGroup = {
    label: string;
    time: string | null;
    isToday: boolean;
};

/**
 * Consecutive days with the same hours collapse into one row, Monday first,
 * as the design draws them: "Monday – Thursday · 17:00 – 23:00".
 */
export function groupHours(
    hours: OpeningHour[],
    dayName: (day: string) => string,
    everyDay: string,
): HoursGroup[] {
    const week = [1, 2, 3, 4, 5, 6, 0]
        .map((dow) => hours.find((hour) => hour.day_of_week === dow))
        .filter((hour): hour is OpeningHour => hour !== undefined);

    const timeOf = (hour: OpeningHour): string | null =>
        hour.opens_at && hour.closes_at
            ? `${clock(hour.opens_at)} – ${clock(hour.closes_at)}`
            : null;

    const runs: OpeningHour[][] = [];

    for (const hour of week) {
        const run = runs[runs.length - 1];

        if (run && timeOf(run[run.length - 1]) === timeOf(hour)) {
            run.push(hour);
        } else {
            runs.push([hour]);
        }
    }

    return runs.map((run) => {
        const first = dayName(run[0].day);
        const last = dayName(run[run.length - 1].day);

        return {
            label:
                run.length === 7
                    ? everyDay
                    : run.length === 1
                      ? first
                      : `${first} – ${last}`,
            time: timeOf(run[0]),
            isToday: run.some((hour) => hour.is_today),
        };
    });
}

/**
 * "14 Marsh Lane, Downtown" → "Downtown", and the same for Arabic
 * addresses, which use the Arabic comma: "14 شارع مارش، وسط المدينة".
 */
export function areaOf(address: string | null): string | null {
    const parts = address?.split(/[,،]/) ?? [];

    if (parts.length < 2) {
        return null;
    }

    const area = parts[parts.length - 1].trim();

    return area ? area : null;
}

/**
 * The design's wordmark: the first word in clay, the rest in ink.
 * "Al Bustan" → "Al" + " Bustan". A one-word name ("البستان") stays ink,
 * so the mark keeps the design's mostly-dark weight.
 */
function Wordmark({ name, size }: { name: string; size: string }) {
    const [first, ...rest] = name.trim().split(/\s+/);

    return (
        <span
            className={`font-display inline-flex items-baseline font-bold tracking-[-0.04em] text-[var(--text-primary)] ${size}`}
        >
            {rest.length > 0 ? (
                <>
                    <span className="text-[var(--clay-500)]">{first}</span>
                    <span>&nbsp;{rest.join(' ')}</span>
                </>
            ) : (
                first
            )}
        </span>
    );
}

// Placeholder art until menu photos are uploaded (#31).
const ITEM_ART = [
    'bg-[linear-gradient(150deg,var(--clay-300),var(--clay-800))]',
    'bg-[linear-gradient(150deg,var(--saffron-300),var(--clay-800))]',
    'bg-[linear-gradient(150deg,var(--herb-300),var(--herb-900))]',
];

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

    const hourGroups = groupHours(
        hours,
        (day) => translations.days[day] ?? day,
        translations.every_day,
    );

    const todaysClose = hours.find((hour) => hour.is_today)?.closes_at ?? null;

    const switchLanguageLabel = translations.switch_language.replace(
        ':language',
        nextLanguageLabel,
    );

    return (
        <GuestLayout>
            <Head title={restaurant.name} />

            {flash?.error && (
                <div role="alert" className="mx-auto max-w-[390px] px-3 pt-3">
                    <div className="border-[var(--danger-solid)]/30 rounded-[var(--radius-md)] border bg-[var(--danger-bg)] px-4 py-3 text-sm text-[var(--danger-fg)]">
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
                    <div className="bg-surface-card relative w-full max-w-[390px] rounded-[var(--radius-xl)] p-4 shadow-xl">
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
                                className="border-[var(--danger-solid)]/30 mt-4 rounded-[var(--radius-md)] border bg-[var(--danger-bg)] px-4 py-3 text-sm text-[var(--danger-fg)]"
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
                    <div className="bg-surface-card relative w-full max-w-[390px] rounded-[var(--radius-xl)] p-5 shadow-xl">
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

            {/* Glass header: wordmark, open/closed badge, language toggle. */}
            <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-[var(--border-subtle)] bg-[var(--alpha-paper-88)] px-[var(--gutter-mobile)] backdrop-blur-[12px]">
                <Wordmark name={restaurant.name} size="text-[19px]" />

                <span className="flex-1" />

                <span
                    className={`text-micro inline-flex h-6 items-center gap-[5px] rounded-[var(--radius-pill)] px-2.5 font-semibold ${
                        open_now
                            ? 'bg-[var(--success-bg)] text-[var(--success-fg)]'
                            : 'bg-[var(--ink-100)] text-[var(--text-tertiary)]'
                    }`}
                >
                    <span
                        aria-hidden="true"
                        className={`size-1.5 rounded-full ${
                            open_now
                                ? 'bg-[var(--herb-500)]'
                                : 'bg-[var(--ink-400)]'
                        }`}
                    />
                    {open_now ? translations.open_now : translations.closed}
                </span>

                {/* A 44px target around the design's small "ع" pill. */}
                <button
                    type="button"
                    onClick={switchLocale}
                    aria-label={switchLanguageLabel}
                    className="-me-2 grid size-11 place-items-center rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-[var(--border-focus-color)]"
                >
                    <span className="text-caption rounded-[var(--radius-pill)] border border-[var(--border-default)] px-2 py-[3px] font-mono text-[var(--text-tertiary)]">
                        {nextLocale === 'ar' ? 'ع' : 'EN'}
                    </span>
                </button>
            </header>

            <main>
                {/* Full-bleed hero, 420 tall, bottom scrim, display-2 statement. */}
                <section className="relative flex h-[420px] items-end overflow-hidden bg-[linear-gradient(150deg,var(--clay-400),var(--clay-900))]">
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 bg-[var(--scrim-bottom)]"
                    />

                    <div className="relative px-[var(--gutter-mobile)] pb-8 pt-6 text-[var(--ink-0)]">
                        {(restaurant.cuisine || areaOf(restaurant.address)) && (
                            <p className="text-micro uppercase tracking-[var(--tracking-eyebrow)] opacity-85">
                                {[
                                    restaurant.cuisine,
                                    areaOf(restaurant.address),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}

                        <h1 className="font-display text-display-2 mb-2.5 mt-2 font-semibold">
                            {restaurant.tagline || translations.hero_fallback}
                        </h1>

                        {restaurant.intro && (
                            <p className="text-body-lg max-w-[30ch] text-[var(--alpha-paper-88)]">
                                {restaurant.intro}
                            </p>
                        )}
                    </div>
                </section>

                {/* Two stacked 54px actions: scan (primary), book (outline). */}
                <section className="flex flex-col gap-3 px-[var(--gutter-mobile)] py-5">
                    <Button
                        type="button"
                        variant="primary"
                        fullWidth
                        onClick={() => setScannerOpen(true)}
                        className="text-body-lg h-[54px] shadow-[var(--shadow-rest)]"
                    >
                        <QrCode aria-hidden="true" className="size-5" />
                        {translations.scan}
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        fullWidth
                        onClick={() => setBookingOpen(true)}
                        className="text-body-lg h-[54px]"
                    >
                        {translations.book}
                    </Button>

                    <p className="text-caption text-center text-[var(--text-tertiary)]">
                        {translations.reassurance}
                    </p>
                </section>

                {/* Tonight's kitchen: horizontal scroller of 230px cards. */}
                <section className="pb-8 pt-6">
                    <div className="flex items-baseline gap-3 px-[var(--gutter-mobile)] pb-3">
                        <h2 className="font-display text-title-1 flex-1 font-semibold tracking-[var(--tracking-title)]">
                            {translations.kitchen_eyebrow}
                        </h2>

                        {todaysClose && (
                            <span className="text-caption font-mono text-[var(--text-tertiary)]">
                                {translations.until.replace(
                                    ':time',
                                    clock(todaysClose),
                                )}
                            </span>
                        )}
                    </div>

                    <div className="flex snap-x gap-3 overflow-x-auto px-[var(--gutter-mobile)] pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        {featured_items.map((item, index) => (
                            <article
                                key={item.id}
                                className="w-[230px] shrink-0 snap-start overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-rest)]"
                            >
                                <div
                                    aria-hidden="true"
                                    className={`h-32 ${ITEM_ART[index % ITEM_ART.length]}`}
                                />

                                <div className="p-3">
                                    {item.chef_flag && (
                                        <p className="text-micro font-semibold uppercase tracking-[var(--tracking-eyebrow)] text-[var(--clay-600)]">
                                            {translations.chef_pick}
                                        </p>
                                    )}

                                    <h3 className="font-display text-title-3 mt-0.5 font-semibold">
                                        {item.name}
                                    </h3>

                                    <Numeric className="text-body mt-1 block font-mono">
                                        {item.price}
                                    </Numeric>
                                </div>
                            </article>
                        ))}

                        {featured_items.length === 0 && (
                            <p className="text-body w-full rounded-[var(--radius-lg)] border border-dashed border-[var(--border-default)] bg-[var(--surface-card)] p-4 text-[var(--text-secondary)]">
                                {translations.no_featured_items}
                            </p>
                        )}
                    </div>
                </section>

                {/* The room: prose at 17px, then opening hours in mono. */}
                <section className="px-[var(--gutter-mobile)] pb-8">
                    <h2 className="font-display text-title-1 mb-3 font-semibold tracking-[var(--tracking-title)]">
                        {translations.room}
                    </h2>

                    <p className="text-body-lg leading-[var(--leading-relaxed)] text-[var(--text-secondary)]">
                        {restaurant.description || translations.room_fallback}
                    </p>

                    <div
                        aria-hidden="true"
                        className="my-5 h-px bg-[var(--border-subtle)]"
                    />

                    <dl className="grid gap-2">
                        {hourGroups.map((group) => (
                            <div
                                key={group.label}
                                className={`text-body flex items-baseline gap-3 ${
                                    group.isToday ? 'font-semibold' : ''
                                }`}
                                aria-current={
                                    group.isToday ? 'date' : undefined
                                }
                            >
                                <dt
                                    className={`flex-1 ${
                                        group.isToday
                                            ? 'text-[var(--text-primary)]'
                                            : 'text-[var(--text-secondary)]'
                                    }`}
                                >
                                    {group.label}
                                </dt>
                                <dd dir="ltr" className="font-mono">
                                    {group.time ?? translations.closed}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>

                {/* Find us: map card, address, phone. */}
                <section className="px-[var(--gutter-mobile)] pb-8">
                    <h2 className="font-display text-title-1 mb-3 font-semibold tracking-[var(--tracking-title)]">
                        {translations.find_us}
                    </h2>

                    <div className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-rest)]">
                        <div
                            aria-hidden="true"
                            className="h-[140px] bg-[linear-gradient(150deg,var(--ink-300),var(--ink-800))]"
                        />

                        <div className="text-body flex flex-col gap-3 p-4 text-[var(--text-secondary)]">
                            {restaurant.address && (
                                <p className="flex items-center gap-3">
                                    <MapPin
                                        aria-hidden="true"
                                        className="size-[18px] shrink-0 text-[var(--clay-500)]"
                                    />
                                    {restaurant.address}
                                </p>
                            )}

                            {restaurant.phone && (
                                <a
                                    href={`tel:${restaurant.phone.replace(/\s+/g, '')}`}
                                    className="-my-3 flex min-h-11 items-center gap-3"
                                >
                                    <Phone
                                        aria-hidden="true"
                                        className="size-[18px] shrink-0 text-[var(--clay-500)]"
                                    />
                                    <span dir="ltr">{restaurant.phone}</span>
                                </a>
                            )}
                        </div>
                    </div>
                </section>
            </main>

            {/* Footer: wordmark, powered-by line. */}
            <footer className="flex flex-col items-center gap-1 border-t border-[var(--border-subtle)] px-[var(--gutter-mobile)] pb-10 pt-6 text-center">
                <Wordmark name={restaurant.name} size="text-[17px]" />

                <p className="text-caption text-[var(--text-tertiary)]">
                    {translations.powered_by}
                </p>
            </footer>
        </GuestLayout>
    );
}
