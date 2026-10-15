<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The languages a code snippet may be written in: the ones tempest/highlight highlights, under the names it knows them
 * by, plus plain text.
 */
enum SnippetLanguage: string
{
    case Apache = 'apache';
    case Bash = 'bash';
    case Blade = 'blade';
    case Css = 'css';
    case Diff = 'diff';
    case Dockerfile = 'dockerfile';
    case DotEnv = 'dotenv';
    case GdScript = 'gdscript';
    case GraphQl = 'graphql';
    case Html = 'html';
    case Http = 'http';
    case Ini = 'ini';
    case JavaScript = 'javascript';
    case Json = 'json';
    case Markdown = 'markdown';
    case Nginx = 'nginx';
    case Php = 'php';
    case Python = 'python';
    case Scss = 'scss';
    case Sql = 'sql';
    case Svelte = 'svelte';
    case Terraform = 'terraform';
    case Twig = 'twig';
    case TypeScript = 'typescript';
    case Vue = 'vue';
    case Xml = 'xml';
    case Yaml = 'yaml';
    case Text = 'text';

    /**
     * The languages to pick from, in the order they are offered.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $language): array => ['value' => $language->value, 'label' => $language->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Apache => 'Apache',
            self::Bash => 'Bash',
            self::Blade => 'Blade',
            self::Css => 'CSS',
            self::Diff => 'Diff',
            self::Dockerfile => 'Dockerfile',
            self::DotEnv => '.env',
            self::GdScript => 'GDScript',
            self::GraphQl => 'GraphQL',
            self::Html => 'HTML',
            self::Http => 'HTTP',
            self::Ini => 'INI',
            self::JavaScript => 'JavaScript',
            self::Json => 'JSON',
            self::Markdown => 'Markdown',
            self::Nginx => 'Nginx',
            self::Php => 'PHP',
            self::Python => 'Python',
            self::Scss => 'SCSS',
            self::Sql => 'SQL',
            self::Svelte => 'Svelte',
            self::Terraform => 'Terraform',
            self::Twig => 'Twig',
            self::TypeScript => 'TypeScript',
            self::Vue => 'Vue',
            self::Xml => 'XML',
            self::Yaml => 'YAML',
            self::Text => 'Plain text',
        };
    }
}
