import { KeyRound, Smartphone, WifiOff } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { MAX_PIN_ATTEMPTS, MIN_PIN_LENGTH } from '@/offline/crypto';
import { registerDevice, unlock, wipeDevice } from '@/offline/session';
import type { UnlockedSession } from '@/offline/session';
import { store as registerRoute } from '@/routes/field/devices';

function PinInput({
    id,
    value,
    onChange,
    autoFocus,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    autoFocus?: boolean;
}) {
    return (
        <Input
            id={id}
            type="password"
            inputMode="numeric"
            autoComplete="off"
            pattern="[0-9]*"
            maxLength={12}
            value={value}
            autoFocus={autoFocus}
            onChange={(event) =>
                onChange(event.target.value.replace(/\D/g, ''))
            }
            className="text-center text-2xl tracking-[0.5em]"
        />
    );
}

export function FieldSetup({
    online,
    onReady,
}: {
    online: boolean;
    onReady: (session: UnlockedSession) => void;
}) {
    const [label, setLabel] = useState('');
    const [pin, setPin] = useState('');
    const [confirm, setConfirm] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setError(null);

        if (pin !== confirm) {
            setError('The two PINs do not match.');

            return;
        }

        setBusy(true);

        try {
            onReady(await registerDevice(label, pin, registerRoute.url()));
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Registration failed.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card className="mx-auto w-full max-w-md">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Smartphone className="size-5" /> Set up this device
                </CardTitle>
            </CardHeader>
            <CardContent>
                {!online ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <WifiOff className="size-4" /> Connect to the internet
                        to register this device for field work.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Your offline PIN encrypts everything stored on this
                            device. It is never sent to the server and cannot be
                            recovered. After {MAX_PIN_ATTEMPTS} wrong attempts,
                            all local data is erased.
                        </p>
                        <FormField label="Device name" htmlFor="label" required>
                            <Input
                                id="label"
                                value={label}
                                onChange={(event) =>
                                    setLabel(event.target.value)
                                }
                                placeholder="e.g. Tablet 07"
                                required
                            />
                        </FormField>
                        <FormField
                            label={`Offline PIN (at least ${MIN_PIN_LENGTH} digits)`}
                            htmlFor="pin"
                            required
                        >
                            <PinInput id="pin" value={pin} onChange={setPin} />
                        </FormField>
                        <FormField
                            label="Repeat PIN"
                            htmlFor="confirm"
                            required
                        >
                            <PinInput
                                id="confirm"
                                value={confirm}
                                onChange={setConfirm}
                            />
                        </FormField>
                        <InputError message={error ?? undefined} />
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={
                                busy || !label || pin.length < MIN_PIN_LENGTH
                            }
                        >
                            {busy ? 'Registering…' : 'Register device'}
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

export function FieldUnlock({
    userName,
    message,
    onUnlocked,
    onWiped,
}: {
    userName: string;
    message?: string | null;
    onUnlocked: (session: UnlockedSession) => void;
    onWiped: () => void;
}) {
    const [pin, setPin] = useState('');
    const [error, setError] = useState<string | null>(message ?? null);
    const [busy, setBusy] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);

        const result = await unlock(pin);
        setBusy(false);
        setPin('');

        if (result.ok) {
            onUnlocked(result.session);

            return;
        }

        if (result.wiped) {
            onWiped();

            return;
        }

        setError(
            `Wrong PIN. ${result.attemptsLeft} attempt${result.attemptsLeft === 1 ? '' : 's'} left before local data is erased.`,
        );
    };

    return (
        <Card className="mx-auto w-full max-w-sm">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <KeyRound className="size-5" /> Unlock field data
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Signed in on this device as {userName}.
                    </p>
                    <FormField label="Offline PIN" htmlFor="unlock-pin">
                        <PinInput
                            id="unlock-pin"
                            value={pin}
                            onChange={setPin}
                            autoFocus
                        />
                    </FormField>
                    <InputError message={error ?? undefined} />
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={busy || pin.length < MIN_PIN_LENGTH}
                    >
                        {busy ? 'Unlocking…' : 'Unlock'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full text-destructive"
                        onClick={async () => {
                            if (
                                window.confirm(
                                    'Erase all field data on this device? Anything not yet synchronised will be lost.',
                                )
                            ) {
                                await wipeDevice();
                                onWiped();
                            }
                        }}
                    >
                        Forgot PIN — erase this device
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
