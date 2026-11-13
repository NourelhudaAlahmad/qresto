import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock3, QrCode, ShieldCheck } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import type { FormEvent } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/qresto/button';
import { Label } from '@/components/qresto/label';
import { fill, useI18n } from '@/lib/i18n';

type StaffMember = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    staff: StaffMember[];
    restaurantSlug: string;
    shiftQrToken?: string | null;
};

type ShiftQrFormData = {
    user_id: string;
    staff: string;
};

export default function ManagerShiftQr({
    staff,
    restaurantSlug,
    shiftQrToken = null,
}: Props) {
    const {
        staff: { shift_qr_display: t },
    } = useI18n();
    const { data, setData, post, processing, errors } =
        useForm<ShiftQrFormData>({
            user_id: '',
            staff: '',
        });

    const selectedStaff = staff.find(
        (member) => String(member.id) === data.user_id,
    );

    const qrPayload =
        shiftQrToken && restaurantSlug
            ? JSON.stringify({
                  type: 'qresto.shift-sign-in',
                  version: 1,
                  restaurant_slug: restaurantSlug,
                  token: shiftQrToken,
              })
            : null;

    function generateQr(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!data.user_id || processing) {
            return;
        }

        post('/auth/shift-qr/issue', {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={t.head} />

            <main className="bg-surface-page font-ui min-h-screen px-5 py-10 sm:px-8">
                <div className="mx-auto w-full max-w-3xl">
                    <a
                        href="/dashboard"
                        className="text-label text-text-secondary hover:text-text-primary mb-8 inline-flex items-center gap-2"
                    >
                        <ArrowLeft className="size-4" />
                        {t.back}
                    </a>

                    <div className="mb-8">
                        <div className="mb-4 flex items-center gap-3">
                            <QrCode className="text-action-primary size-7" />

                            <span className="font-display text-title-1 text-text-primary font-bold">
                                <span className="text-text-brand">Q</span>
                                {'Resto'}
                            </span>
                        </div>

                        <h1 className="font-display text-display-3 text-text-primary">
                            {t.title}
                        </h1>

                        <p className="text-body text-text-secondary mt-3">
                            {t.intro}
                        </p>
                    </div>

                    <div className="bg-surface-card shadow-card rounded-2xl p-6 sm:p-8">
                        <form
                            onSubmit={generateQr}
                            className="flex flex-col gap-5"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="staff-member">
                                    {t.select_label}
                                </Label>

                                <select
                                    id="staff-member"
                                    value={data.user_id}
                                    onChange={(event) =>
                                        setData('user_id', event.target.value)
                                    }
                                    required
                                    className="border-border-subtle bg-surface-card text-body text-text-primary focus:border-action-primary h-12 w-full rounded-lg border px-4 outline-none"
                                >
                                    <option value="">
                                        {t.select_placeholder}
                                    </option>

                                    {staff.map((member) => (
                                        <option
                                            key={member.id}
                                            value={member.id}
                                        >
                                            {member.name} — {member.email}
                                        </option>
                                    ))}
                                </select>

                                <InputError message={errors.user_id} />
                                <InputError message={errors.staff} />
                            </div>

                            <Button
                                type="submit"
                                variant="primary"
                                fullWidth
                                loading={processing}
                                disabled={!data.user_id || processing}
                                className="h-12"
                            >
                                <QrCode className="size-5" />
                                {t.generate}
                            </Button>
                        </form>

                        {qrPayload && (
                            <div className="border-border-subtle mt-8 border-t pt-8">
                                <div className="text-center">
                                    <h2 className="font-display text-title-1 text-text-primary">
                                        {t.ready}
                                    </h2>

                                    <p className="text-body text-text-secondary mt-2">
                                        {selectedStaff
                                            ? fill(t.code_for, {
                                                  name: selectedStaff.name,
                                              })
                                            : t.scan_to_sign_in}
                                    </p>
                                </div>

                                <div className="mx-auto mt-6 flex w-fit max-w-full justify-center rounded-xl bg-white p-5">
                                    <QRCodeSVG
                                        value={qrPayload}
                                        size={240}
                                        level="M"
                                        marginSize={2}
                                        title={t.qr_title}
                                    />
                                </div>

                                <div className="text-label text-text-secondary mt-6 flex items-center justify-center gap-2">
                                    <Clock3 className="size-4" />
                                    {t.valid_for}
                                </div>

                                <p className="text-caption text-text-tertiary mt-3 text-center">
                                    {t.once_only}
                                </p>
                            </div>
                        )}

                        {staff.length === 0 && (
                            <p className="text-body text-text-secondary mt-5">
                                {t.no_staff}
                            </p>
                        )}
                    </div>

                    <div className="text-text-secondary mt-6 flex items-start gap-3">
                        <ShieldCheck className="text-action-primary mt-1 size-5 shrink-0" />

                        <p className="text-caption leading-relaxed">
                            {t.security}
                        </p>
                    </div>
                </div>
            </main>
        </>
    );
}
