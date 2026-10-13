<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizer
{
    private SymfonyHtmlSanitizer $sanitizer;

    public function __construct()
    {
        $allowedTags = [
            'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 's', 'strike',
            'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'img', 'a', 'figure', 'figcaption', 'hr',
        ];

        $config = (new HtmlSanitizerConfig)
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['http', 'https'])
            ->allowMediaSchemes(['http', 'https']);

        foreach ($allowedTags as $tag) {
            $config = $config->allowElement($tag);
        }

        $config = $config
            ->allowAttribute('class', $allowedTags)
            ->allowAttribute('href', ['a'])
            ->allowAttribute('title', ['a', 'img'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('src', ['img'])
            ->allowAttribute('alt', ['img'])
            ->allowAttribute('width', ['img'])
            ->allowAttribute('height', ['img']);

        $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return $this->sanitizer->sanitize($html);
    }
}
