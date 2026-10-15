import { useFormContext, usePage } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { SnippetLanguageOption } from '@/types';

const maxCodeLength = 4000;
const maxLineCount = 200;
const indentation = '  ';

export function SnippetFields() {
    const errors =
        useFormContext<{
            snippet: { language: string; filename: string; code: string };
        }>()?.errors ?? {};
    const { snippetLanguages } = usePage<{
        snippetLanguages: SnippetLanguageOption[];
    }>().props;
    const [code, setCode] = useState('');

    const lineCount = code === '' ? 0 : code.split('\n').length;
    const characterCount = code.length;

    const indentOnTab = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key !== 'Tab' ||
            event.shiftKey ||
            event.altKey ||
            event.ctrlKey ||
            event.metaKey
        ) {
            return;
        }

        event.preventDefault();

        const textarea = event.currentTarget;

        textarea.setRangeText(
            indentation,
            textarea.selectionStart,
            textarea.selectionEnd,
            'end',
        );
        setCode(textarea.value);
    };

    return (
        <div className="mt-2 space-y-2" data-test="snippet-fields">
            <div className="flex gap-2">
                <div className="w-36 shrink-0">
                    <label htmlFor="snippet-language" className="sr-only">
                        Language
                    </label>
                    <Select name="snippet[language]" defaultValue="php">
                        <SelectTrigger
                            id="snippet-language"
                            aria-invalid={
                                errors['snippet.language'] ? true : undefined
                            }
                            className="w-full"
                            data-test="snippet-language-select"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {snippetLanguages.map((language) => (
                                <SelectItem
                                    key={language.value}
                                    value={language.value}
                                >
                                    {language.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={errors['snippet.language']}
                        className="mt-1"
                    />
                </div>
                <div className="min-w-0 flex-1">
                    <label htmlFor="snippet-filename" className="sr-only">
                        File name
                    </label>
                    <Input
                        id="snippet-filename"
                        name="snippet[filename]"
                        maxLength={100}
                        placeholder="File name (optional)"
                        autoComplete="off"
                        spellCheck={false}
                        aria-invalid={
                            errors['snippet.filename'] ? true : undefined
                        }
                        className="font-mono"
                    />
                    <InputError
                        message={errors['snippet.filename']}
                        className="mt-1"
                    />
                </div>
            </div>

            <div>
                <label htmlFor="snippet-code" className="sr-only">
                    Code
                </label>
                <textarea
                    id="snippet-code"
                    name="snippet[code]"
                    value={code}
                    onChange={(event) => setCode(event.target.value)}
                    onKeyDown={indentOnTab}
                    rows={4}
                    maxLength={maxCodeLength}
                    wrap="off"
                    placeholder="Paste or write your code"
                    autoComplete="off"
                    autoCapitalize="off"
                    autoCorrect="off"
                    spellCheck={false}
                    aria-invalid={errors['snippet.code'] ? true : undefined}
                    aria-describedby="snippet-code-count"
                    className="block field-sizing-content max-h-96 min-h-28 w-full resize-none overflow-auto rounded-md border bg-transparent px-3 py-2 font-mono text-[13px] leading-6 outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                    data-test="snippet-code-input"
                />
                <div className="mt-1 flex items-start justify-between gap-4">
                    <InputError message={errors['snippet.code']} />
                    <span
                        id="snippet-code-count"
                        className="ml-auto shrink-0 font-mono text-xs text-muted-foreground"
                        data-test="snippet-code-count"
                    >
                        <span
                            className={cn({
                                'text-destructive': lineCount > maxLineCount,
                            })}
                        >
                            {lineCount}/{maxLineCount} lines
                        </span>
                        <span aria-hidden> · </span>
                        <span
                            className={cn({
                                'text-destructive':
                                    characterCount > maxCodeLength,
                            })}
                        >
                            {characterCount}/{maxCodeLength} characters
                        </span>
                    </span>
                </div>
            </div>
        </div>
    );
}
