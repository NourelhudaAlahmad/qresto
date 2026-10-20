import { router } from '@inertiajs/react';
import type { Html5Qrcode } from 'html5-qrcode';
import { Camera, QrCode, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

import { Button } from '@/components/qresto/button';

type ShiftQrPayload = {
    type: 'qresto.shift-sign-in';
    version: 1;
    restaurant_slug: string;
    token: string;
};

type Props = {
    onClose: () => void;
};

function parseShiftQr(value: string): ShiftQrPayload | null {
    try {
        const parsed: unknown = JSON.parse(value);

        if (
            typeof parsed !== 'object' ||
            parsed === null ||
            Array.isArray(parsed)
        ) {
            return null;
        }

        const data = parsed as Record<string, unknown>;

        if (
            data.type !== 'qresto.shift-sign-in' ||
            data.version !== 1 ||
            typeof data.restaurant_slug !== 'string' ||
            !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(data.restaurant_slug) ||
            typeof data.token !== 'string' ||
            !/^[A-Za-z0-9]{64}$/.test(data.token)
        ) {
            return null;
        }

        return {
            type: 'qresto.shift-sign-in',
            version: 1,
            restaurant_slug: data.restaurant_slug,
            token: data.token,
        };
    } catch {
        return null;
    }
}

async function stopScanner(scanner: Html5Qrcode): Promise<void> {
    try {
        await scanner.stop();
    } catch {
        // Camera may still be starting.
    }

    try {
        scanner.clear();
    } catch {
        // Scanner may already be cleared.
    }
}

export function ShiftQrScanner({ onClose }: Props) {
    const generatedId = useId();
    const scannerId = `shift-qr-reader-${generatedId.replace(/:/g, '')}`;

    const scannerRef = useRef<Html5Qrcode | null>(null);
    const submittedRef = useRef(false);

    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        let disposed = false;
        let startingScanner: Html5Qrcode | null = null;

        const startScanner = async () => {
            try {
                setError(null);

                const { Html5Qrcode } = await import('html5-qrcode');

                if (disposed) {
                    return;
                }

                const scanner = new Html5Qrcode(scannerId);

                startingScanner = scanner;
                scannerRef.current = scanner;

                await scanner.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: {
                            width: 240,
                            height: 240,
                        },
                    },
                    (decodedText) => {
                        console.log('QR detected:', decodedText.length);

                        if (disposed || submittedRef.current) {
                            return;
                        }

                        const payload = parseShiftQr(decodedText);

                        if (payload === null) {
                            setError(
                                'This is not a valid QRestO shift sign-in code.',
                            );

                            return;
                        }

                        submittedRef.current = true;
                        setError(null);
                        setSubmitting(true);

                        void stopScanner(scanner).then(() => {
                            if (disposed) {
                                return;
                            }

                            router.post(
                                '/auth/shift-qr',
                                {
                                    token: payload.token,
                                    restaurant_slug: payload.restaurant_slug,
                                },
                                {
                                    preserveScroll: true,
                                    onError: (errors) => {
                                        setError(
                                            errors.token ??
                                                'Shift QR sign-in failed. Please try again.',
                                        );
                                        submittedRef.current = false;
                                    },
                                    onFinish: () => {
                                        if (!disposed) {
                                            setSubmitting(false);
                                        }
                                    },
                                },
                            );
                        });
                    },
                    () => undefined,
                );

                if (disposed) {
                    scannerRef.current = null;
                    await stopScanner(scanner);
                }
            } catch {
                if (!disposed) {
                    setError(
                        'The camera could not be opened. Allow camera access and try again.',
                    );
                }

                if (startingScanner) {
                    await stopScanner(startingScanner);
                }
            }
        };

        void startScanner();

        return () => {
            disposed = true;

            const scanner = scannerRef.current;
            scannerRef.current = null;

            if (scanner) {
                void stopScanner(scanner);
            }
        };
    }, [scannerId]);

    return (
        <div className="border-border-subtle bg-surface-card mt-4 rounded-xl border p-4">
            <div className="mb-4 flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <QrCode className="text-action-primary size-5" />

                    <div>
                        <h2 className="text-label text-text-primary font-semibold">
                            Scan shift QR
                        </h2>

                        <p className="text-caption text-text-secondary mt-1">
                            Point your camera at the shift code provided by your
                            manager.
                        </p>
                    </div>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClose}
                    disabled={submitting}
                    aria-label="Close QR scanner"
                >
                    <X className="size-4" />
                </Button>
            </div>

            <div
                id={scannerId}
                className="bg-surface-page w-full overflow-hidden rounded-lg"
                aria-label="Shift QR camera scanner"
            />

            {error && (
                <p role="alert" className="text-caption text-text-danger mt-3">
                    {error}
                </p>
            )}

            {submitting && (
                <p
                    role="status"
                    className="text-caption text-text-secondary mt-3 flex items-center gap-2"
                >
                    <Camera className="size-4" />
                    Verifying your shift QR...
                </p>
            )}

            <p className="text-caption text-text-tertiary mt-3">
                Shift codes expire after 5 minutes and can only be used once.
            </p>
        </div>
    );
}
