import { useFormContext } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import type { Release } from '@/types';

export function ReleaseFields() {
    const errors = useFormContext<{ release: Release }>()?.errors ?? {};

    return (
        <div className="mt-2 space-y-2" data-test="release-fields">
            <div className="flex gap-2">
                <div className="min-w-0 flex-1">
                    <label htmlFor="release-project" className="sr-only">
                        Project
                    </label>
                    <Input
                        id="release-project"
                        name="release[project]"
                        maxLength={50}
                        placeholder="Project, e.g. laravel/framework"
                        autoComplete="off"
                        aria-invalid={
                            errors['release.project'] ? true : undefined
                        }
                    />
                    <InputError
                        message={errors['release.project']}
                        className="mt-1"
                    />
                </div>
                <div className="w-32 shrink-0">
                    <label htmlFor="release-version" className="sr-only">
                        Version
                    </label>
                    <Input
                        id="release-version"
                        name="release[version]"
                        maxLength={30}
                        placeholder="v1.0.0"
                        autoComplete="off"
                        spellCheck={false}
                        aria-invalid={
                            errors['release.version'] ? true : undefined
                        }
                        className="font-mono"
                    />
                    <InputError
                        message={errors['release.version']}
                        className="mt-1"
                    />
                </div>
            </div>

            <div>
                <label htmlFor="release-url" className="sr-only">
                    Release link
                </label>
                <Input
                    id="release-url"
                    name="release[url]"
                    type="url"
                    maxLength={255}
                    placeholder="Link to the release (optional)"
                    autoComplete="off"
                    aria-invalid={errors['release.url'] ? true : undefined}
                />
                <InputError message={errors['release.url']} className="mt-1" />
            </div>

            <div>
                <label htmlFor="release-notes" className="sr-only">
                    Release notes
                </label>
                <textarea
                    id="release-notes"
                    name="release[notes]"
                    rows={3}
                    maxLength={1000}
                    placeholder="Release notes (optional)"
                    aria-invalid={errors['release.notes'] ? true : undefined}
                    className="block field-sizing-content min-h-20 w-full resize-none rounded-md border bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                />
                <InputError
                    message={errors['release.notes']}
                    className="mt-1"
                />
            </div>
        </div>
    );
}
