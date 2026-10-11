export type HoneypotData = {
    enabled: boolean;
    nameFieldName: string;
    validFromFieldName: string;
    encryptedValidFrom: string;
};

/**
 * Hidden spam-trap fields checked by spatie/laravel-honeypot on the server.
 * People never see or fill them; simple bots do.
 */
export function HoneypotFields({
    honeypot,
}: {
    honeypot: HoneypotData | null | undefined;
}) {
    if (!honeypot?.enabled) {
        return null;
    }

    return (
        <div
            aria-hidden="true"
            style={{
                position: 'absolute',
                left: '-10000px',
                width: 1,
                height: 1,
                overflow: 'hidden',
            }}
        >
            <label htmlFor={honeypot.nameFieldName}>
                Leave this field empty
            </label>
            <input
                id={honeypot.nameFieldName}
                name={honeypot.nameFieldName}
                type="text"
                tabIndex={-1}
                autoComplete="off"
                defaultValue=""
            />
            <input
                name={honeypot.validFromFieldName}
                type="text"
                tabIndex={-1}
                autoComplete="off"
                defaultValue={honeypot.encryptedValidFrom}
            />
        </div>
    );
}
