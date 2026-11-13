import { Head, router } from '@inertiajs/react';
import { Printer, QrCode, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { DataTable } from '@/components/qresto/data-table';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Switch } from '@/components/qresto/switch';
import { showToast } from '@/components/qresto/toast';
import { Tooltip } from '@/components/qresto/tooltip';
import { formatMinor } from '@/lib/formatMoney';
import { fill, useI18n } from '@/lib/i18n';

type Details = {
    name: string;
    tagline: string | null;
    cuisine: string | null;
    address: string | null;
    phone: string | null;
    timezone: string;
    currency: string;
    service_charge_pct: number;
    default_locale: string;
    supported_locales: string[];
};

type Day = {
    day_of_week: number;
    closed: boolean;
    opens_at: string | null;
    closes_at: string | null;
};

type Table = {
    id: number;
    number: string;
    seats: number;
    sort_order: number;
    qr_url: string;
    busy: boolean;
    unpaid: boolean;
};

type Props = {
    restaurant: Details;
    hours: Day[];
    tables: Table[];
    options: {
        currencies: string[];
        locales: { code: string; label: string }[];
        timezones: string[];
    };
};

type Errors = Record<string, string>;

const QR_SHEET = '/app/settings/tables/qr-sheet';

const SELECT =
    'text-body h-11 w-full rounded-[var(--radius-md)] border border-[var(--border-default)] bg-[var(--surface-card)] px-3 text-[var(--text-primary)] outline-none focus-visible:border-[var(--border-focus-color)] focus-visible:shadow-[var(--ring-focus)]';

/**
 * What a percentage does to a $50 bill, the way the guest will see it.
 * `sentence` holds :bill, :service and :total.
 */
export function serviceExample(
    pct: number,
    currency: string,
    sentence: string,
): string {
    const bill = 5000;
    const service = Math.round((bill * (Number.isFinite(pct) ? pct : 0)) / 100);

    return fill(sentence, {
        bill: formatMinor(bill, currency),
        service: formatMinor(service, currency),
        total: formatMinor(bill + service, currency),
    });
}

function Panel({
    title,
    description,
    aside,
    children,
}: {
    title: string;
    description?: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="min-w-0 rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-rest)]">
            <div className="mb-4 flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                    <h2 className="font-display text-title-2 font-semibold">
                        {title}
                    </h2>
                    {description && (
                        <p className="text-caption mt-0.5 text-[var(--text-tertiary)]">
                            {description}
                        </p>
                    )}
                </div>
                {aside}
            </div>
            {children}
        </section>
    );
}

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && !error && (
                <span className="text-caption text-[var(--text-tertiary)]">
                    {hint}
                </span>
            )}
            {error && (
                <span
                    role="alert"
                    className="text-caption text-[var(--berry-500)]"
                >
                    {error}
                </span>
            )}
        </div>
    );
}

function DetailsForm({
    restaurant,
    options,
}: Pick<Props, 'restaurant' | 'options'>) {
    const {
        staff: { admin_restaurant: t },
    } = useI18n();
    const [form, setForm] = useState<Details>(restaurant);
    const [errors, setErrors] = useState<Errors>({});
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof Details>(key: K, value: Details[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    function toggleLocale(code: string, on: boolean) {
        const next = on
            ? [...form.supported_locales, code]
            : form.supported_locales.filter((locale) => locale !== code);

        setForm((current) => ({
            ...current,
            supported_locales: next,
            default_locale: next.includes(current.default_locale)
                ? current.default_locale
                : (next[0] ?? current.default_locale),
        }));
    }

    function save() {
        router.put('/app/settings/restaurant', form, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => {
                setErrors({});
                showToast({
                    type: 'success',
                    title: t.details_saved,
                    description: t.details_saved_body,
                });
            },
            onError: (next) => setErrors(next),
        });
    }

    const text = (key: keyof Details, label: string) => (
        <Field id={`restaurant-${key}`} label={label} error={errors[key]}>
            <Input
                id={`restaurant-${key}`}
                value={(form[key] as string | null) ?? ''}
                aria-invalid={errors[key] ? true : undefined}
                onChange={(event) => set(key, event.target.value as never)}
            />
        </Field>
    );

    return (
        <Panel title={t.details} description={t.details_hint}>
            <div className="grid grid-cols-1 gap-4 min-[720px]:grid-cols-2">
                {text('name', t.name)}
                {text('tagline', t.tagline)}
                {text('cuisine', t.cuisine)}
                {text('phone', t.phone)}
                <div className="min-[720px]:col-span-2">
                    {text('address', t.address)}
                </div>

                <Field
                    id="restaurant-timezone"
                    label={t.timezone}
                    error={errors.timezone}
                >
                    <select
                        id="restaurant-timezone"
                        className={SELECT}
                        value={form.timezone}
                        onChange={(event) =>
                            set('timezone', event.target.value)
                        }
                    >
                        {options.timezones.map((zone) => (
                            <option key={zone} value={zone}>
                                {zone}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    id="restaurant-currency"
                    label={t.currency}
                    error={errors.currency}
                >
                    <select
                        id="restaurant-currency"
                        className={SELECT}
                        value={form.currency}
                        onChange={(event) =>
                            set('currency', event.target.value)
                        }
                    >
                        {options.currencies.map((currency) => (
                            <option key={currency} value={currency}>
                                {currency}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    id="restaurant-service"
                    label={t.service_charge}
                    error={errors.service_charge_pct}
                    hint={
                        <Numeric data-testid="service-example">
                            {serviceExample(
                                form.service_charge_pct,
                                form.currency,
                                t.service_example,
                            )}
                        </Numeric>
                    }
                >
                    <Input
                        id="restaurant-service"
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={30}
                        step={0.5}
                        dir="ltr"
                        className="font-mono"
                        value={form.service_charge_pct}
                        aria-invalid={
                            errors.service_charge_pct ? true : undefined
                        }
                        onChange={(event) =>
                            set(
                                'service_charge_pct',
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>

                <div className="flex flex-col gap-1.5">
                    <span className="text-label font-medium text-[var(--text-primary)]">
                        {t.languages}
                    </span>

                    <div className="flex flex-wrap gap-4">
                        {options.locales.map((locale) => (
                            <label
                                key={locale.code}
                                className="text-body flex min-h-11 items-center gap-2"
                            >
                                <Checkbox
                                    checked={form.supported_locales.includes(
                                        locale.code,
                                    )}
                                    onCheckedChange={(checked) =>
                                        toggleLocale(
                                            locale.code,
                                            checked === true,
                                        )
                                    }
                                    aria-label={fill(t.offer_language, {
                                        language: locale.label,
                                    })}
                                />
                                {locale.label}
                            </label>
                        ))}
                    </div>

                    <label
                        htmlFor="restaurant-default-locale"
                        className="text-caption text-[var(--text-tertiary)]"
                    >
                        {t.default_language}
                    </label>
                    <select
                        id="restaurant-default-locale"
                        className={SELECT}
                        value={form.default_locale}
                        onChange={(event) =>
                            set('default_locale', event.target.value)
                        }
                    >
                        {options.locales
                            .filter((locale) =>
                                form.supported_locales.includes(locale.code),
                            )
                            .map((locale) => (
                                <option key={locale.code} value={locale.code}>
                                    {locale.label}
                                </option>
                            ))}
                    </select>

                    {(errors.supported_locales || errors.default_locale) && (
                        <span
                            role="alert"
                            className="text-caption text-[var(--berry-500)]"
                        >
                            {errors.supported_locales ?? errors.default_locale}
                        </span>
                    )}
                </div>
            </div>

            <div className="mt-5 flex justify-end">
                <Button
                    type="button"
                    variant="primary"
                    loading={saving}
                    onClick={save}
                >
                    {t.save_details}
                </Button>
            </div>
        </Panel>
    );
}

function HoursEditor({ hours }: { hours: Day[] }) {
    const {
        staff: { admin_restaurant: t },
    } = useI18n();
    const [days, setDays] = useState<Day[]>(hours);
    const [errors, setErrors] = useState<Errors>({});
    const [saving, setSaving] = useState(false);

    const update = (index: number, change: Partial<Day>) =>
        setDays((current) =>
            current.map((day, i) =>
                i === index ? { ...day, ...change } : day,
            ),
        );

    function save() {
        router.put(
            '/app/settings/hours',
            { hours: days },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => {
                    setErrors({});
                    showToast({
                        type: 'success',
                        title: t.hours_saved,
                    });
                },
                onError: (next) => setErrors(next),
            },
        );
    }

    return (
        <Panel title={t.hours} description={t.hours_hint}>
            <div className="flex flex-col">
                {days.map((day, index) => {
                    const name = t.days[day.day_of_week];
                    const error =
                        errors[`hours.${index}.closes_at`] ??
                        errors[`hours.${index}.opens_at`];

                    return (
                        <div
                            key={day.day_of_week}
                            className="flex flex-wrap items-center gap-3 border-b border-[var(--border-subtle)] py-2 last:border-0"
                        >
                            <span className="text-body w-28 font-medium">
                                {name}
                            </span>

                            <label className="text-caption flex min-h-11 items-center gap-2 text-[var(--text-secondary)]">
                                <Switch
                                    checked={!day.closed}
                                    aria-label={fill(t.day_open, { day: name })}
                                    onCheckedChange={(open) =>
                                        update(index, {
                                            closed: !open,
                                            opens_at: open
                                                ? (day.opens_at ?? '17:00')
                                                : day.opens_at,
                                            closes_at: open
                                                ? (day.closes_at ?? '23:00')
                                                : day.closes_at,
                                        })
                                    }
                                />
                                {day.closed ? t.closed : t.open}
                            </label>

                            {!day.closed && (
                                <div
                                    dir="ltr"
                                    className="flex items-center gap-2 font-mono"
                                >
                                    <Input
                                        type="time"
                                        aria-label={fill(t.day_opens, {
                                            day: name,
                                        })}
                                        className="w-[120px]"
                                        value={day.opens_at ?? ''}
                                        onChange={(event) =>
                                            update(index, {
                                                opens_at: event.target.value,
                                            })
                                        }
                                    />
                                    <span aria-hidden="true">–</span>
                                    <Input
                                        type="time"
                                        aria-label={fill(t.day_closes, {
                                            day: name,
                                        })}
                                        className="w-[120px]"
                                        value={day.closes_at ?? ''}
                                        aria-invalid={error ? true : undefined}
                                        onChange={(event) =>
                                            update(index, {
                                                closes_at: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                            )}

                            {error && (
                                <span
                                    role="alert"
                                    className="text-caption w-full text-[var(--berry-500)]"
                                >
                                    {error}
                                </span>
                            )}
                        </div>
                    );
                })}
            </div>

            <div className="mt-5 flex justify-end">
                <Button
                    type="button"
                    variant="primary"
                    loading={saving}
                    onClick={save}
                >
                    {t.save_hours}
                </Button>
            </div>
        </Panel>
    );
}

type Confirm = { kind: 'reissue' | 'retire'; table: Table } | null;

function TablesList({ tables }: { tables: Table[] }) {
    const {
        staff: { admin_restaurant: t },
    } = useI18n();
    const [drafts, setDrafts] = useState<Record<number, Partial<Table>>>({});
    const [adding, setAdding] = useState({ number: '', seats: 4 });
    const [errors, setErrors] = useState<Errors>({});
    const [busy, setBusy] = useState<string | null>(null);
    const [confirm, setConfirm] = useState<Confirm>(null);

    const value = <K extends keyof Table>(table: Table, key: K): Table[K] =>
        (drafts[table.id]?.[key] as Table[K] | undefined) ?? table[key];

    const dirty = (table: Table) =>
        drafts[table.id] !== undefined &&
        (['number', 'seats', 'sort_order'] as const).some(
            (key) => value(table, key) !== table[key],
        );

    const options = (key: string, onSuccess?: () => void) => ({
        preserveScroll: true,
        preserveState: true,
        only: ['tables'],
        onStart: () => setBusy(key),
        onFinish: () => setBusy(null),
        onSuccess: () => {
            setErrors({});
            onSuccess?.();
        },
        onError: (next: Errors) => {
            setErrors(next);

            const message = next.table ?? Object.values(next)[0];

            if (message) {
                showToast({
                    type: 'error',
                    title: t.action_failed,
                    description: message,
                });
            }
        },
    });

    function saveRow(table: Table) {
        router.patch(
            `/app/settings/tables/${table.id}`,
            {
                number: value(table, 'number'),
                seats: value(table, 'seats'),
                sort_order: value(table, 'sort_order'),
            },
            options(`save-${table.id}`, () => {
                setDrafts((current) => {
                    const next = { ...current };
                    delete next[table.id];

                    return next;
                });
                showToast({
                    type: 'success',
                    title: fill(t.table_saved, {
                        table: value(table, 'number'),
                    }),
                });
            }),
        );
    }

    function add() {
        router.post(
            '/app/settings/tables',
            adding,
            options('add', () => {
                showToast({
                    type: 'success',
                    title: fill(t.table_added, { table: adding.number }),
                    description: t.table_added_body,
                });
                setAdding({ number: '', seats: 4 });
            }),
        );
    }

    function confirmAction() {
        if (!confirm) {
            return;
        }

        const { kind, table } = confirm;

        router.post(
            `/app/settings/tables/${table.id}/${kind}`,
            {},
            options(`${kind}-${table.id}`, () => {
                setConfirm(null);
                showToast(
                    kind === 'reissue'
                        ? {
                              type: 'success',
                              title: fill(t.reissued, { table: table.number }),
                              description: t.reissued_body,
                          }
                        : {
                              type: 'success',
                              title: fill(t.retired, { table: table.number }),
                          },
                );
            }),
        );
    }

    const rows = tables.map((table) => {
        const retireBlocked = table.busy
            ? t.retire_busy
            : table.unpaid
              ? t.retire_unpaid
              : null;
        const labelled = (template: string) =>
            fill(template, { table: table.number });

        return {
            number: (
                <Input
                    aria-label={labelled(t.table_number)}
                    value={value(table, 'number')}
                    className="font-mono"
                    onChange={(event) =>
                        setDrafts((current) => ({
                            ...current,
                            [table.id]: {
                                ...current[table.id],
                                number: event.target.value,
                            },
                        }))
                    }
                />
            ),
            seats: (
                <Input
                    type="number"
                    min={1}
                    max={30}
                    aria-label={labelled(t.table_seats)}
                    value={value(table, 'seats')}
                    className="font-mono"
                    onChange={(event) =>
                        setDrafts((current) => ({
                            ...current,
                            [table.id]: {
                                ...current[table.id],
                                seats: Number(event.target.value),
                            },
                        }))
                    }
                />
            ),
            sort: (
                <Input
                    type="number"
                    min={0}
                    aria-label={labelled(t.table_order)}
                    value={value(table, 'sort_order')}
                    className="font-mono"
                    onChange={(event) =>
                        setDrafts((current) => ({
                            ...current,
                            [table.id]: {
                                ...current[table.id],
                                sort_order: Number(event.target.value),
                            },
                        }))
                    }
                />
            ),
            actions: (
                <div className="flex items-center justify-end gap-1">
                    {dirty(table) && (
                        <Button
                            type="button"
                            size="sm"
                            variant="primary"
                            loading={busy === `save-${table.id}`}
                            onClick={() => saveRow(table)}
                        >
                            {t.save}
                        </Button>
                    )}

                    <Button asChild size="sm" variant="ghost">
                        <a
                            href={`${QR_SHEET}?table=${table.id}`}
                            target="_blank"
                            rel="noopener"
                            aria-label={labelled(t.print_qr)}
                        >
                            <QrCode className="size-4" aria-hidden />
                        </a>
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        aria-label={labelled(t.reissue_qr)}
                        onClick={() => setConfirm({ kind: 'reissue', table })}
                    >
                        <RotateCcw className="size-4" aria-hidden />
                    </Button>

                    <Tooltip content={retireBlocked ?? t.retire_hint}>
                        <span tabIndex={retireBlocked ? 0 : -1}>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={retireBlocked !== null}
                                aria-label={labelled(t.retire_label)}
                                onClick={() =>
                                    setConfirm({ kind: 'retire', table })
                                }
                            >
                                <Trash2
                                    className="size-4 text-[var(--berry-500)]"
                                    aria-hidden
                                />
                            </Button>
                        </span>
                    </Tooltip>
                </div>
            ),
        };
    });

    return (
        <Panel
            title={t.tables}
            description={
                tables.length === 1
                    ? t.tables_summary_one
                    : fill(t.tables_summary_other, { count: tables.length })
            }
            aside={
                <Button asChild variant="outline" size="sm">
                    <a href={QR_SHEET} target="_blank" rel="noopener">
                        <Printer className="size-4" aria-hidden />
                        {t.print_all}
                    </a>
                </Button>
            }
        >
            <DataTable
                columns={[
                    { key: 'number', label: t.col_table },
                    { key: 'seats', label: t.col_seats },
                    { key: 'sort', label: t.col_order },
                    { key: 'actions', label: '' },
                ]}
                rows={rows}
            />

            <div className="mt-4 flex flex-wrap items-end gap-3">
                <Field
                    id="new-table-number"
                    label={t.new_table}
                    error={errors.number}
                >
                    <Input
                        id="new-table-number"
                        placeholder="13"
                        className="w-28 font-mono"
                        value={adding.number}
                        onChange={(event) =>
                            setAdding({ ...adding, number: event.target.value })
                        }
                    />
                </Field>

                <Field
                    id="new-table-seats"
                    label={t.seats}
                    error={errors.seats}
                >
                    <Input
                        id="new-table-seats"
                        type="number"
                        min={1}
                        max={30}
                        className="w-24 font-mono"
                        value={adding.seats}
                        onChange={(event) =>
                            setAdding({
                                ...adding,
                                seats: Number(event.target.value),
                            })
                        }
                    />
                </Field>

                <Button
                    type="button"
                    variant="outline"
                    loading={busy === 'add'}
                    disabled={adding.number.trim() === ''}
                    onClick={add}
                >
                    {t.add_table}
                </Button>
            </div>

            {confirm && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open && busy === null) {
                            setConfirm(null);
                        }
                    }}
                    title={fill(
                        confirm.kind === 'reissue'
                            ? t.reissue_title
                            : t.retire_title,
                        { table: confirm.table.number },
                    )}
                    description={
                        confirm.kind === 'reissue'
                            ? t.reissue_body
                            : t.retire_body
                    }
                >
                    <div className="flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            disabled={busy !== null}
                            onClick={() => setConfirm(null)}
                        >
                            {t.cancel}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="flex-1"
                            loading={busy !== null}
                            onClick={confirmAction}
                        >
                            {confirm.kind === 'reissue'
                                ? t.reissue_code
                                : t.retire_table}
                        </Button>
                    </div>
                </ResponsiveOverlay>
            )}
        </Panel>
    );
}

export default function RestaurantSettings({
    restaurant,
    hours,
    tables,
    options,
}: Props) {
    const {
        staff: { pages },
    } = useI18n();

    return (
        <>
            <Head title={pages.restaurant} />

            <div className="flex flex-col gap-4">
                <DetailsForm restaurant={restaurant} options={options} />

                <div className="grid grid-cols-1 items-start gap-4 min-[1100px]:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                    <HoursEditor hours={hours} />
                    <TablesList tables={tables} />
                </div>
            </div>
        </>
    );
}
