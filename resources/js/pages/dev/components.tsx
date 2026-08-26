import { Head } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { Radio, RadioGroup } from '@/components/qresto/radio';
import { RadioCard } from '@/components/qresto/radio-card';
import { SearchField } from '@/components/qresto/search-field';
import { Switch } from '@/components/qresto/switch';
import { Textarea } from '@/components/qresto/textarea';

export default function Components() {
    const [quantity, setQuantity] = React.useState(9);
    const [available, setAvailable] = React.useState(true);

    return (
        <>
            <Head title="QResto Components" />

            <main className="min-h-screen bg-surface-page p-8 text-text-primary">
                <div className="mx-auto max-w-6xl space-y-12">
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
                                        {available ? 'Available' : 'Unavailable'}
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

                        <RadioGroup defaultValue="medium" className="max-w-xl">
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
                </div>
            </main>
        </>
    );
}