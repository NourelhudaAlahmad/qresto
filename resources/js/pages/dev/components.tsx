import { Head } from '@inertiajs/react';
import * as React from 'react';

import { Avatar } from '@/components/qresto/avatar';
import { Badge } from '@/components/qresto/badge';
import { Button } from '@/components/qresto/button';
import { Card } from '@/components/qresto/card';
import { Checkbox } from '@/components/qresto/checkbox';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { DataTable } from '@/components/qresto/data-table';
import { Divider } from '@/components/qresto/divider';
import { Drawer } from '@/components/qresto/drawer';
import { EmptyState } from '@/components/qresto/empty-state';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { OrderTicket } from '@/components/qresto/order-ticket';
import { PriceSummary } from '@/components/qresto/price-summary';
import { ProgressTrail } from '@/components/qresto/progress-trail';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Radio, RadioGroup } from '@/components/qresto/radio';
import { RadioCard } from '@/components/qresto/radio-card';
import { SearchField } from '@/components/qresto/search-field';
import { SectionHead } from '@/components/qresto/section-head';
import { Skeleton } from '@/components/qresto/skeleton';
import { Stat } from '@/components/qresto/stat';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Switch } from '@/components/qresto/switch';
import { TabBar } from '@/components/qresto/tab-bar';
import { Textarea } from '@/components/qresto/textarea';
import { Tooltip } from '@/components/qresto/tooltip';
import { showToast } from '@/components/qresto/toast';

export default function Components() {
    const [quantity, setQuantity] = React.useState(9);
    const [available, setAvailable] = React.useState(true);
    const [drawerOpen, setDrawerOpen] = React.useState(false);
    const [overlayOpen, setOverlayOpen] = React.useState(false);

    const tableColumns = [
        { key: 'date' as const, label: 'Date' },
        { key: 'waiter' as const, label: 'Waiter' },
        { key: 'orders' as const, label: 'Orders', numeric: true },
        { key: 'net' as const, label: 'Net', numeric: true },
    ];

    const tableRows = [
        {
            date: '23 Aug',
            waiter: 'Nadia',
            orders: '22',
            net: '$2,418.00',
        },
        {
            date: '22 Aug',
            waiter: 'Omar',
            orders: '19',
            net: '$2,104.20',
        },
        {
            date: '21 Aug',
            waiter: 'Lin',
            orders: '14',
            net: '$1,562.75',
        },
    ];

    return (
        <>
            <Head title="QResto Components" />

<main className="min-h-screen w-full overflow-x-hidden bg-surface-page p-4 text-text-primary sm:p-6 lg:p-8"><div className="mx-auto w-full max-w-6xl space-y-8 sm:space-y-10 lg:space-y-12">
                    <header>
                        <p className="text-label font-medium uppercase tracking-eyebrow text-text-brand">
                            QResto
                        </p>

                        <h1 className="mt-2 text-display-2 font-semibold">
                            Component Playground
                        </h1>

                        <p className="mt-2 max-w-prose text-text-secondary">
                            Local-only visual regression page for QResto
                            primitives.
                        </p>
                    </header>

                    <section className="space-y-5">
                        <h2 className="text-title-1 font-semibold">
                            Buttons
                        </h2>

                        <div className="flex flex-wrap gap-4">
                            <Button variant="primary">Primary</Button>
                            <Button variant="secondary">Secondary</Button>
                            <Button variant="outline">Outline</Button>
                            <Button variant="ghost">Ghost</Button>
                            <Button variant="destructive">
                                Destructive
                            </Button>
                            <Button variant="disabled" disabled>
                                Sold out
                            </Button>
                        </div>

                        <div className="flex flex-wrap items-center gap-4">
                            <Button variant="primary" size="sm">
                                Small
                            </Button>
                            <Button variant="primary" size="md">
                                Medium
                            </Button>
                            <Button variant="primary" size="lg">
                                Large
                            </Button>
                        </div>

                        <div className="max-w-md">
                            <Button variant="primary" size="lg" fullWidth>
                                Guest Dock
                            </Button>
                        </div>

                        <div className="flex flex-wrap gap-4">
                            <Button variant="primary" loading>
                                Loading
                            </Button>
                        </div>
                    </section>

                    <section className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-3 rounded-lg border border-border-subtle bg-surface-card p-6">
                            <h2 className="text-title-2 font-semibold">
                                Input
                            </h2>

                            <Label htmlFor="restaurant-name">
                                Restaurant name
                            </Label>

                            <Input
                                id="restaurant-name"
                                placeholder="Enter restaurant name"
                            />
                        </div>

                        <div className="space-y-3 rounded-lg border border-border-subtle bg-surface-card p-6">
                            <h2 className="text-title-2 font-semibold">
                                Search
                            </h2>

                            <SearchField
                                placeholder="Search products..."
                                aria-label="Search products"
                            />
                        </div>

                        <div className="space-y-3 rounded-lg border border-border-subtle bg-surface-card p-6">
                            <h2 className="text-title-2 font-semibold">
                                Textarea
                            </h2>

                            <Label htmlFor="notes">Notes</Label>

                            <Textarea
                                id="notes"
                                placeholder="Write notes..."
                            />
                        </div>

                        <div className="space-y-3 rounded-lg border border-border-subtle bg-surface-card p-6">
                            <h2 className="text-title-2 font-semibold">
                                Switch
                            </h2>

                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-label font-medium">
                                        Menu availability
                                    </p>

                                    <p className="text-caption text-text-secondary">
                                        {available
                                            ? 'Available'
                                            : 'Unavailable'}
                                    </p>
                                </div>

                                <Switch
                                    checked={available}
                                    onCheckedChange={setAvailable}
                                    aria-label="Menu availability"
                                />
                            </div>
                        </div>
                    </section>

                    <section className="grid gap-6 md:grid-cols-2">
                        <div className="rounded-lg border border-border-subtle bg-surface-card p-6">
                            <h2 className="mb-4 text-title-2 font-semibold">
                                Checkbox
                            </h2>

                            <label className="flex items-center gap-2">
                                <Checkbox id="cheese" />

                                <span className="text-label">
                                    Extra cheese
                                </span>
                            </label>
                        </div>

                        <div className="rounded-lg border border-border-subtle bg-surface-card p-6">
                            <h2 className="mb-4 text-title-2 font-semibold">
                                Radio
                            </h2>

                            <RadioGroup defaultValue="cash">
                                <label className="flex items-center gap-2">
                                    <Radio value="cash" />

                                    <span className="text-label">
                                        Cash
                                    </span>
                                </label>

                                <label className="flex items-center gap-2">
                                    <Radio value="card" />

                                    <span className="text-label">
                                        Card
                                    </span>
                                </label>
                            </RadioGroup>
                        </div>
                    </section>

                    <section className="space-y-5">
                        <h2 className="text-title-1 font-semibold">
                            Radio Cards
                        </h2>

                        <RadioGroup
                            defaultValue="medium"
                            className="max-w-xl"
                        >
                            <RadioCard
                                value="small"
                                title="Small"
                                description="6 pieces"
                            />

                            <RadioCard
                                value="medium"
                                title="Medium"
                                description="10 pieces"
                            />

                            <RadioCard
                                value="large"
                                title="Large"
                                description="16 pieces"
                            />
                        </RadioGroup>
                    </section>

                    <section className="space-y-5">
                        <h2 className="text-title-1 font-semibold">
                            Quantity Stepper
                        </h2>

                        <div className="flex flex-wrap items-center gap-8">
                            <div>
                                <p className="mb-2 text-caption text-text-secondary">
                                    36px
                                </p>

                                <QuantityStepper
                                    size="sm"
                                    value={quantity}
                                    min={1}
                                    onChange={setQuantity}
                                />
                            </div>

                            <div>
                                <p className="mb-2 text-caption text-text-secondary">
                                    44px
                                </p>

                                <QuantityStepper
                                    size="md"
                                    value={quantity}
                                    min={1}
                                    onChange={setQuantity}
                                />
                            </div>
                        </div>

                        <div className="qr-numeric text-title-2 font-bold">
                            Current value: {quantity}
                        </div>
                    </section>

                    <section className="rounded-lg border border-border-subtle bg-surface-card p-6">
                        <h2 className="mb-4 text-title-1 font-semibold">
                            Numeric Test
                        </h2>

                        <div className="space-y-2">
                            <span
                                data-numeric
                                className="block text-title-2 font-bold"
                            >
                                $54.35
                            </span>

                            <span
                                data-numeric
                                className="block text-title-2 font-bold"
                            >
                                $120.00
                            </span>

                            <span
                                data-numeric
                                className="block text-title-2 font-bold"
                            >
                                $8.50
                            </span>
                        </div>
                    </section>

                    <section className="space-y-5">
                        <h2 className="text-title-1 font-semibold">
                            QResto Components
                        </h2>

                        <div className="grid gap-6 md:grid-cols-2">
                            <Card>
                                <SectionHead
                                    title="Floor team"
                                    count={4}
                                />

                                <div className="my-4">
                                    <Divider />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Avatar initials="NR" />

                                    <div className="min-w-0 flex-1">
                                        <p className="text-label font-medium">
                                            Nadia Rahman
                                        </p>

                                        <p className="text-caption text-text-secondary">
                                            Waiter · On shift
                                        </p>
                                    </div>

                                    <Badge>4</Badge>
                                </div>
                            </Card>

                            <Card>
                                <SectionHead
                                    title="Menu filters"
                                    count={3}
                                />

                                <div className="mt-4 flex flex-wrap gap-2">
                                    <ChipFilter active>
                                        All
                                    </ChipFilter>

                                    <ChipFilter>
                                        Preparing
                                    </ChipFilter>

                                    <ChipFilter type="category">
                                        Ready
                                    </ChipFilter>
                                </div>
                            </Card>

                            <Card>
                                <Stat
                                    label="Covers tonight"
                                    value="64"
                                    hint="+12% vs last Friday"
                                    hintTone="positive"
                                />
                            </Card>

                            <Card>
                                <SectionHead title="Loading" />

                                <div className="mt-4 space-y-2">
                                    <Skeleton width="60%" />
                                    <Skeleton width="85%" />
                                    <Skeleton width="40%" />
                                </div>
                            </Card>

                            <Card>
                                <SectionHead title="Empty state" />

                                <EmptyState
                                    headline="No orders yet"
                                    body="New orders will appear here."
                                />
                            </Card>

                            <Card>
                                <SectionHead title="Price summary" />

                                <div className="mt-4">
                                    <PriceSummary
                                        subtotal="$100.00"
                                        service="$12.50"
                                        discount="-$10.00"
                                        tip="$5.00"
                                        total="$107.50"
                                    />
                                </div>
                            </Card>

                            <Card>
                                <SectionHead title="Progress trail" />

                                <div className="mt-4">
                                    <ProgressTrail
                                        items={[
                                            {
                                                title: 'Order placed',
                                                time: '19:42',
                                                detail: 'Sent to the pass',
                                                state: 'done',
                                            },
                                            {
                                                title: 'In the kitchen',
                                                time: '19:44',
                                                detail:
                                                    'Kofta on the grill',
                                                state: 'current',
                                            },
                                            {
                                                title: 'Ready at the pass',
                                                time: '~19:58',
                                                detail:
                                                    'Nadia collects it',
                                                state: 'upcoming',
                                            },
                                            {
                                                title: 'At your table',
                                                detail: 'Enjoy',
                                                state: 'upcoming',
                                            },
                                        ]}
                                    />
                                </div>
                            </Card>

                            <Card>
                                <SectionHead title="Order ticket" />

                                <div className="mt-4">
                                    <OrderTicket
                                        code="#A-1043"
                                        elapsed="14 min"
                                        table="Table 12"
                                        guest="Rami"
                                        status="preparing"
                                        items={[
                                            {
                                                qty: '2×',
                                                name: 'Lamb kofta',
                                                note: 'sumac onions',
                                            },
                                            {
                                                qty: '1×',
                                                name: 'Charred aubergine',
                                                note: 'no tahini',
                                            },
                                        ]}
                                        paid
                                        total="$54.35"
                                        onAdvance={() => {}}
                                        onOverflow={() => {}}
                                    />
                                </div>
                            </Card>

                            <Card>
                                <SectionHead title="Data table" />

                                <div className="mt-4">
                                    <DataTable
                                        columns={tableColumns}
                                        rows={tableRows}
                                        total="$6,084.95"
                                    />
                                </div>
                            </Card>
                        </div>
                    </section>

                    <section className="space-y-5">
                        <h2 className="text-title-1 font-semibold">
                            Overlays & feedback
                        </h2>

                        <div className="flex flex-wrap gap-3">
                            <Button
                                variant="primary"
                                onClick={() => setDrawerOpen(true)}
                            >
                                Open Drawer
                            </Button>

                            <Button
                                variant="secondary"
                                onClick={() => setOverlayOpen(true)}
                            >
                                Open Responsive Overlay
                            </Button>

                            <Button
                                variant="outline"
                                onClick={() =>
                                    showToast({
                                        type: 'success',
                                        title: 'Saved successfully',
                                    })
                                }
                            >
                                Success Toast
                            </Button>

                            <Button
                                variant="outline"
                                onClick={() =>
                                    showToast({
                                        type: 'info',
                                        title: 'Order ready',
                                        description: 'Table 12',
                                        action: {
                                            label: 'View',
                                            onClick: () => {},
                                        },
                                    })
                                }
                            >
                                Info Toast
                            </Button>

                            <Button
                                variant="destructive"
                                onClick={() =>
                                    showToast({
                                        type: 'error',
                                        title: 'Could not save dish',
                                    })
                                }
                            >
                                Error Toast
                            </Button>

                            <Tooltip content="More actions">
                                <Button variant="ghost">
                                    Tooltip
                                </Button>
                            </Tooltip>
                        </div>

                        <StickyDock>
                            <Button
                                variant="primary"
                                size="lg"
                                fullWidth
                            >
                                View order · 3 items
                                <span className="ml-2 font-mono">
                                    $42.00
                                </span>
                            </Button>
                        </StickyDock>

                        <TabBar
                            items={[
                                {
                                    label: 'Menu',
                                    active: true,
                                    icon: <span>🍽</span>,
                                },
                                {
                                    label: 'Order',
                                    badge: 3,
                                    icon: <span>🛍</span>,
                                },
                                {
                                    label: 'Status',
                                    icon: <span>●</span>,
                                },
                            ]}
                        />
                    </section>

                    <Drawer
                        open={drawerOpen}
                        onOpenChange={setDrawerOpen}
                        title="Add waiter"
                    >
                        <div className="space-y-4">
                            <Label htmlFor="drawer-name">
                                Waiter name
                            </Label>

                            <Input
                                id="drawer-name"
                                placeholder="Nadia Rahman"
                            />

                            <Button
                                variant="primary"
                                fullWidth
                                onClick={() => setDrawerOpen(false)}
                            >
                                Save waiter
                            </Button>
                        </div>
                    </Drawer>

                    <ResponsiveOverlay
                        open={overlayOpen}
                        onOpenChange={setOverlayOpen}
                        title="Confirm order"
                    >
                        <div className="space-y-4">
                            <p className="text-body text-text-secondary">
                                Are you sure you want to continue?
                            </p>

                            <div className="flex gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setOverlayOpen(false)
                                    }
                                >
                                    Cancel
                                </Button>

                                <Button
                                    variant="primary"
                                    onClick={() =>
                                        setOverlayOpen(false)
                                    }
                                >
                                    Confirm
                                </Button>
                            </div>
                        </div>
                    </ResponsiveOverlay>
                </div>
            </main>
        </>
    );
}