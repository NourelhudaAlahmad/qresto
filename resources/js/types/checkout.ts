/** The guest checkout copy, from lang/{en,ar}/checkout.php. */
export type CheckoutTranslations = {
    title: string;
    secured: string;
    back: string;
    verify_title: string;
    verify_body: string;
    verify_cta: string;
    verify_failed: string;
    check_card: string;
    card_loading: string;
    wallet_soon: string;
    or_choose: string;
    card_title: string;
    card_description: string;
    card_number: string;
    card_expiry: string;
    card_cvc: string;
    card_safe: string;
    cash_title: string;
    cash_description: string;
    pos_title: string;
    pos_description: string;
    deferred_title: string;
    deferred_body: string;
    tip_title: string;
    tip_shared: string;
    tip_none: string;
    promo: string;
    promo_placeholder: string;
    promo_applied: string;
    promo_remove: string;
    promo_apply: string;
    split_toggle: string;
    split_title: string;
    split_body: string;
    split_share: string;
    split_note: string;
    split_summary: string;
    pay: string;
    send_to_kitchen: string;
    footnote_deferred: string;
    footnote_card: string;
    subtotal: string;
    service: string;
    tip: string;
    tip_pct: string;
    discount: string;
    split: string;
    total: string;
};

/** Puts values into a translation's :placeholders. */
export function fill(
    text: string,
    values: Record<string, string | number>,
): string {
    return Object.entries(values).reduce(
        (result, [key, value]) => result.split(`:${key}`).join(String(value)),
        text,
    );
}
