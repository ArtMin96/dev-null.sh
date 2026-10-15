<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SnippetLanguage;
use App\Services\CodeHighlighter;
use Carbon\CarbonInterface;
use Database\Factories\SnippetFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read SnippetLanguage $language
 * @property-read string|null $filename
 * @property-read string $code
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Visible([
    'language',
    'filename',
    'code',
])]
final class Snippet extends Model
{
    /** @use HasFactory<SnippetFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'language' => SnippetLanguage::class,
            'filename' => 'string',
            'code' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * A snippet is shown with its language's name, and its code highlighted: the raw code stays, to be copied.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'language_label' => $this->language->label(),
            'highlighted_html' => resolve(CodeHighlighter::class)->highlightSnippet($this),
        ];
    }
}
