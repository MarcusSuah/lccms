import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { GeoAreaPicker } from './geo-area-picker';

export type CommunityBlockValue = {
    community_id: number | null;
    block_id: number | null;
    label: string;
};

/**
 * Chooses the community (required) and optionally a block for records that
 * belong to a community, such as facilities, infrastructure and leaders.
 */
export function CommunityBlockField({
    value,
    onChange,
    error,
    blockRequired = false,
    idPrefix = 'location',
}: {
    value: CommunityBlockValue;
    onChange: (value: CommunityBlockValue) => void;
    error?: string;
    blockRequired?: boolean;
    idPrefix?: string;
}) {
    const [changing, setChanging] = useState(value.community_id === null);

    if (!changing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm">
                <span>{value.label}</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setChanging(true)}
                >
                    Change location
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <GeoAreaPicker
                maxLevel="block"
                idPrefix={idPrefix}
                onChange={(selection) => {
                    const communityId = selection?.path.community ?? null;
                    const blockId = selection?.path.block ?? null;

                    onChange({
                        community_id: communityId,
                        block_id: blockId,
                        label: selection?.label ?? '',
                    });
                }}
            />
            <p className="text-xs text-muted-foreground">
                {blockRequired
                    ? 'Select down to the block.'
                    : 'Select at least the community. The block is optional.'}
            </p>
            <InputError message={error} />
        </div>
    );
}
