<?php

declare(strict_types=1);

test('draft document detail hides review actions even when reviewer permission is present', function (): void {
    $html = view('events.teams._documents', [
        'kind' => 'draft',
        'canReview' => true,
        'documents' => [[
            'code' => 'mandate', 'name' => 'Surat Mandat', 'status' => 'pending',
            'notes' => '<script>unsafe</script>', 'exists' => true,
            'download_url' => '/private/mandate', 'review_url' => '/private/review', 'version' => 'current',
        ]],
    ])->render();

    expect($html)->toContain('Surat Mandat', '/private/mandate', '&lt;script&gt;unsafe&lt;/script&gt;')
        ->not->toContain('action="/private/review"', 'name="status"', '<script>unsafe</script>');
});

test('submitted document detail exposes separate verified and rejected decisions with version', function (): void {
    $html = view('events.teams._documents', [
        'kind' => 'participant',
        'canReview' => true,
        'documents' => [[
            'code' => 'mandate', 'name' => 'Surat Mandat', 'status' => 'pending',
            'notes' => null, 'exists' => true,
            'download_url' => '/private/mandate', 'review_url' => '/private/review', 'version' => 'current',
        ]],
    ])->render();

    expect($html)->toContain('action="/private/review"', 'value="verified"', 'value="rejected"', 'name="version"', 'value="current"');
});
