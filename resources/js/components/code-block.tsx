import { Check, Copy, FileCode } from 'lucide-react';
import type { ComponentProps, MouseEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

const collapsedLineCount = 15;

/**
 * Highlighted code under a slim header: the file's name when it has one, its language, and a button that copies the
 * code as written. The server escapes the code while highlighting it, so its HTML is safe to render as is.
 */
export function CodeBlock({
    code,
    html,
    languageLabel,
    filename = null,
    hasLineNumbers = false,
    className,
    ...props
}: Omit<ComponentProps<'figure'>, 'children'> & {
    code: string;
    html: string;
    languageLabel: string;
    filename?: string | null;
    hasLineNumbers?: boolean;
}) {
    const [isExpanded, setIsExpanded] = useState(false);
    const lineCount = code.split('\n').length;
    const isCollapsed = lineCount > collapsedLineCount && !isExpanded;

    return (
        <figure
            className={cn(
                'grid grid-cols-[minmax(0,1fr)_auto] overflow-hidden rounded-lg border bg-card whitespace-normal',
                className,
            )}
            data-test="code-block"
            {...props}
        >
            <figcaption className="flex min-w-0 items-center gap-2 border-b bg-muted/50 py-0.5 pl-3 font-mono text-xs">
                {filename && (
                    <>
                        <FileCode
                            aria-hidden
                            className="size-3.5 shrink-0 text-muted-foreground"
                        />
                        <span
                            className="min-w-0 truncate"
                            title={filename}
                            data-test="code-block-filename"
                        >
                            {filename}
                        </span>
                    </>
                )}
                <span
                    className="shrink-0 text-muted-foreground"
                    data-test="code-block-language"
                >
                    {languageLabel}
                </span>
            </figcaption>
            <div className="flex items-center border-b bg-muted/50 py-0.5 pr-0.5 pl-2">
                <CopyCodeButton code={code} />
            </div>

            <div className="relative col-span-2 min-w-0">
                <div
                    className={cn(
                        'flex overflow-x-auto font-mono text-[13px] leading-5 wrap-normal',
                        {
                            'max-h-[calc(15lh+1rem)] overflow-y-hidden':
                                isCollapsed,
                        },
                    )}
                    data-test="code-block-code"
                >
                    {hasLineNumbers && (
                        <pre
                            aria-hidden
                            className="sticky left-0 shrink-0 border-r bg-card py-2 pr-2 pl-3 text-right text-muted-foreground/70 select-none"
                        >
                            {Array.from(
                                { length: lineCount },
                                (_, index) => index + 1,
                            ).join('\n')}
                        </pre>
                    )}
                    <pre className="flex-1 px-3 py-2">
                        <code dangerouslySetInnerHTML={{ __html: html }} />
                    </pre>
                </div>

                {isCollapsed && (
                    <div className="absolute inset-x-0 bottom-0 flex justify-center bg-linear-to-t from-card via-card/90 to-transparent pt-10 pb-2">
                        <button
                            type="button"
                            onClick={() => setIsExpanded(true)}
                            className="rounded-md border bg-card px-2.5 py-1 text-[13px] font-medium text-primary shadow-xs hover:bg-accent"
                            data-test="code-block-expand-button"
                        >
                            Show all {lineCount} lines
                        </button>
                    </div>
                )}
            </div>
        </figure>
    );
}

function CopyCodeButton({ code }: { code: string }) {
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeout = setTimeout(() => setIsCopied(false), 2000);

        return () => clearTimeout(timeout);
    }, [isCopied]);

    const copyCode = async (event: MouseEvent<HTMLButtonElement>) => {
        event.stopPropagation();

        if (await copy(code)) {
            setIsCopied(true);

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy the code
            </span>,
        );
    };

    const Icon = isCopied ? Check : Copy;

    return (
        <>
            <button
                type="button"
                onClick={copyCode}
                aria-label="Copy code"
                className="flex h-6 items-center gap-1 rounded-md px-1.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:-outline-offset-2"
                data-test="code-block-copy-button"
            >
                <Icon aria-hidden className="size-3.5 shrink-0" />
                {/* Both labels take up the same space, so the header never shifts. */}
                <span className="grid">
                    <span
                        className={cn('col-start-1 row-start-1', {
                            invisible: isCopied,
                        })}
                    >
                        Copy
                    </span>
                    <span
                        className={cn('col-start-1 row-start-1', {
                            invisible: !isCopied,
                        })}
                    >
                        Copied
                    </span>
                </span>
            </button>
            <span role="status" className="sr-only">
                {isCopied ? 'Copied' : ''}
            </span>
        </>
    );
}
