import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/heading';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import { useI18n } from '@/lib/i18n';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const EmptyState = ({ title, body }: { title: string; body: string }) => {
    return (
        <div className="p-8 text-center">
            <div className="bg-muted mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl">
                <KeyRound className="text-muted-foreground h-7 w-7" />
            </div>
            <p className="font-medium">{title}</p>
            <p className="text-muted-foreground mt-1 text-sm">{body}</p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const {
        staff: {
            account: { passkeys: t },
        },
    } = useI18n();
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t.title}
                description={t.description}
            />

            <div className="border-border overflow-hidden rounded-lg border">
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                        />
                    ))
                ) : (
                    <EmptyState title={t.empty_title} body={t.empty_body} />
                )}
            </div>

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </div>
    );
}
