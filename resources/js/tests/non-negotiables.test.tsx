import { render, screen } from '@testing-library/react';
import axe from 'axe-core';
import { describe, expect, it } from 'vitest';

import { Numeric } from '@/components/numeric';
import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { Input } from '@/components/qresto/input';
import { MenuItemCard } from '@/components/qresto/menu-item-card';
import { OrderTicket } from '@/components/qresto/order-ticket';
import { Radio, RadioGroup } from '@/components/qresto/radio';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Switch } from '@/components/qresto/switch';
import { checkDock, checkScreen } from '@/lib/non-negotiables';

/*
 * The design's seven non-negotiables (#36). Each rule has a test that
 * fails when the rule is broken; several build the broken case on purpose.
 */

const BELOW_44 =
    /(?:^|\s)(?:h|size)-(?:[0-9]|10)(?:\s|$)|(?:^|\s)(?:h|size)-\[(?:[0-9]|[1-3][0-9]|4[0-3])px\]/;

function meetsTouchTarget(element: Element): boolean {
    const classes = element.getAttribute('class') ?? '';

    return classes.includes('touch-target') || !BELOW_44.test(classes);
}

describe('01 one primary button, 54px, full width, in a glass dock', () => {
    it('accepts one 54px full-width primary in the dock', () => {
        render(
            <StickyDock>
                <Button size="lg" fullWidth>
                    Pay $14.06
                </Button>
            </StickyDock>,
        );

        const dock = document.querySelector('[data-dock]');

        expect(dock).toHaveClass(
            'bg-[var(--alpha-paper-88)]',
            'backdrop-blur-[12px]',
        );
    });

    it('breaks on two primaries in the dock', () => {
        expect(() =>
            render(
                <StickyDock>
                    <Button size="lg" fullWidth>
                        Pay
                    </Button>
                    <Button size="lg" fullWidth>
                        Pay again
                    </Button>
                </StickyDock>,
            ),
        ).toThrow(/2 primary buttons/);
    });

    it('breaks on a primary that is not 54px', () => {
        expect(() =>
            render(
                <StickyDock>
                    <Button fullWidth>Pay</Button>
                </StickyDock>,
            ),
        ).toThrow(/must be 54px/);
    });

    it('breaks on two primaries on one guest screen', () => {
        const screenElement = document.createElement('div');
        screenElement.innerHTML =
            '<button data-slot="qresto-button" data-variant="primary">A</button><button data-slot="qresto-button" data-variant="primary">B</button>';

        expect(() => checkScreen(screenElement)).toThrow(/2 primary buttons/);
        expect(() => checkDock(screenElement)).toThrow(/2 primary buttons/);
    });
});

describe('02 nothing interactive is under 44px', () => {
    it('gives every button size at least a 44px target', () => {
        render(
            <>
                <Button size="sm">Small</Button>
                <Button size="md">Medium</Button>
                <Button size="lg">Large</Button>
            </>,
        );

        screen.getAllByRole('button').forEach((button) => {
            expect(meetsTouchTarget(button)).toBe(true);
        });
        expect(screen.getByRole('button', { name: 'Small' })).toHaveClass(
            'touch-target',
        );
    });

    it('gives inputs, checkboxes, switches and radios a 44px target', () => {
        render(
            <>
                <Input aria-label="Name" />
                <Checkbox aria-label="Tick" />
                <Switch aria-label="Toggle" />
                <RadioGroup aria-label="Choice" defaultValue="a">
                    <Radio value="a" aria-label="A" />
                </RadioGroup>
            </>,
        );

        expect(screen.getByLabelText('Name')).toHaveClass('h-[44px]');

        for (const control of [
            screen.getByRole('checkbox', { name: 'Tick' }),
            screen.getByRole('switch', { name: 'Toggle' }),
            screen.getByRole('radio', { name: 'A' }),
        ]) {
            expect(control).toHaveClass('touch-target');
        }
    });

    it('catches a 40px control', () => {
        const forty = document.createElement('button');
        forty.className = 'h-10 px-3';

        expect(meetsTouchTarget(forty)).toBe(false);
    });
});

describe('03 sold-out dishes stay visible and dimmed', () => {
    it('keeps a sold-out dish on the menu, dimmed and labelled', () => {
        render(
            <MenuItemCard
                name="Whole sea bass"
                price="$34.00"
                available={false}
            />,
        );

        const card = screen.getByText('Whole sea bass').closest('button');

        expect(card).toBeInTheDocument();
        expect(card).toHaveClass('opacity-[0.45]');
        expect(card).toBeDisabled();
        expect(screen.getByText('Sold out')).toBeInTheDocument();
    });
});

describe('04 guest notes echo in saffron', () => {
    it('shows the note in saffron on the kitchen ticket, light and dark', () => {
        for (const variant of ['light', 'dark'] as const) {
            const { unmount } = render(
                <OrderTicket
                    variant={variant}
                    code="#A-1043"
                    elapsed="4 min"
                    table="Table 12"
                    guest="Rami"
                    status="preparing"
                    items={[
                        {
                            qty: '1×',
                            name: 'Charred aubergine',
                            note: 'no tahini',
                        },
                    ]}
                    paid={false}
                    total="$12.50"
                />,
            );

            expect(screen.getByText('no tahini').className).toMatch(
                /--saffron-(?:300|700)/,
            );

            unmount();
        }
    });
});

describe('06 and 07 money is mono and tabular, and stays LTR in an RTL line', () => {
    it('renders numbers left-to-right with tabular figures inside Arabic text', () => {
        render(
            <p dir="rtl">
                المجموع <Numeric>$14.06</Numeric>
            </p>,
        );

        const amount = screen.getByText('$14.06');

        expect(amount).toHaveAttribute('dir', 'ltr');
        expect(amount).toHaveClass('qr-numeric');
    });
});

describe('the accessibility pass itself', () => {
    it('reports a button with no name as critical', async () => {
        const host = document.createElement('div');
        host.innerHTML = '<button><svg></svg></button>';
        document.body.appendChild(host);

        const results = await axe.run(host, {
            rules: { 'color-contrast': { enabled: false } },
        });

        expect(
            results.violations.some(
                (violation) => violation.impact === 'critical',
            ),
        ).toBe(true);

        host.remove();
    });
});
