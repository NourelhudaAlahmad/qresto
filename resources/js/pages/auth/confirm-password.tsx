import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/lib/i18n';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    const {
        staff: {
            account: { confirm_password: t, fields },
        },
    } = useI18n();

    setLayoutProps({
        title: t.title,
        description: t.description,
    });

    return (
        <>
            <Head title={t.title} />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label={t.passkey}
                loadingLabel={t.passkey_loading}
                separator={t.passkey_separator}
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{fields.password}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder={fields.password}
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {t.submit}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}
