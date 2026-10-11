import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { index as leadershipIndex } from '@/routes/admin/leadership';
import { store, update } from '@/routes/admin/leadership/positions';
import type { Option } from '@/types';

type Position = {
    id: number;
    code: string;
    title: string;
    level: string;
    single_holder: boolean;
    is_active: boolean;
    sort_order: number;
};

function PositionRow({
    position,
    levels,
}: {
    position: Position | null;
    levels: Option[];
}) {
    const form = useForm({
        code: position?.code ?? '',
        title: position?.title ?? '',
        level: position?.level ?? 'community',
        single_holder: position?.single_holder ?? true,
        is_active: position?.is_active ?? true,
        sort_order: position?.sort_order ?? 0,
    });

    return (
        <form
            className="grid items-end gap-2 border-b py-3 last:border-0 sm:grid-cols-[1fr_1.5fr_1fr_auto_auto_auto]"
            onSubmit={(event) => {
                event.preventDefault();
                form.submit(position ? update(position.id) : store(), {
                    preserveScroll: true,
                    onSuccess: () => !position && form.reset(),
                });
            }}
        >
            <FormField
                label="Code"
                htmlFor={`code-${position?.id ?? 'new'}`}
                error={form.errors.code}
            >
                <Input
                    id={`code-${position?.id ?? 'new'}`}
                    className="font-mono"
                    value={form.data.code}
                    onChange={(event) =>
                        form.setData('code', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Title"
                htmlFor={`title-${position?.id ?? 'new'}`}
                error={form.errors.title}
            >
                <Input
                    id={`title-${position?.id ?? 'new'}`}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Level"
                htmlFor={`level-${position?.id ?? 'new'}`}
                error={form.errors.level}
            >
                <NativeSelect
                    id={`level-${position?.id ?? 'new'}`}
                    value={form.data.level}
                    onChange={(event) =>
                        form.setData('level', event.target.value)
                    }
                >
                    {levels.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <label className="flex items-center gap-2 pb-2 text-sm">
                <Checkbox
                    checked={form.data.single_holder}
                    onCheckedChange={(checked) =>
                        form.setData('single_holder', checked === true)
                    }
                />
                One holder
            </label>
            <label className="flex items-center gap-2 pb-2 text-sm">
                <Checkbox
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                Active
            </label>
            <Button type="submit" size="sm" disabled={form.processing}>
                {position ? 'Save' : 'Add'}
            </Button>
        </form>
    );
}

export default function Positions({
    positions,
    levels,
}: {
    positions: Position[];
    levels: Option[];
}) {
    return (
        <>
            <Head title="Leadership positions" />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Community governance"
                    title="Leadership positions"
                    description="Configure the locally recognised titles. Deactivate a position instead of removing it, so history is kept."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Positions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {positions.map((position) => (
                            <PositionRow
                                key={position.id}
                                position={position}
                                levels={levels}
                            />
                        ))}
                        <PositionRow position={null} levels={levels} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Positions.layout = {
    breadcrumbs: [{ title: 'Leadership', href: leadershipIndex() }],
};
