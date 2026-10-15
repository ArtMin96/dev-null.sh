import { CodeBlock } from '@/components/code-block';
import type { Snippet } from '@/types';

export function SnippetCard({ snippet }: { snippet: Snippet }) {
    return (
        <CodeBlock
            code={snippet.code}
            html={snippet.highlighted_html}
            languageLabel={snippet.language_label}
            filename={snippet.filename}
            hasLineNumbers
            className="mt-3"
            data-test="snippet-card"
        />
    );
}
