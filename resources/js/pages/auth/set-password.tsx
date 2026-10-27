import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import PasswordInput from '@/components/password-input';
import { Button } from '@/components/qresto/button';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

/**
 * Reached from the set-password link a manager sends when they add someone
 * to the shift (#27). The link expires; the server says so if it has.
 */
export default function SetPassword({ token, email, passwordRules }: Props) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post('/staff/invitation', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Set your password" />

            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="email">Work email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        dir="ltr"
                        readOnly
                    />
                    {form.errors.email && (
                        <p
                            role="alert"
                            className="text-caption text-[var(--berry-500)]"
                        >
                            {form.errors.email}
                        </p>
                    )}
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="password">New password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autoComplete="new-password"
                        autoFocus
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        passwordrules={passwordRules}
                    />
                    {form.errors.password && (
                        <p className="text-caption text-[var(--berry-500)]">
                            {form.errors.password}
                        </p>
                    )}
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="password_confirmation">
                        Confirm password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        passwordrules={passwordRules}
                    />
                </div>

                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    fullWidth
                    loading={form.processing}
                >
                    Set password
                </Button>
            </form>
        </>
    );
}

SetPassword.layout = {
    title: 'Set your password',
    description: 'Choose a password to sign in to QResto.',
};
