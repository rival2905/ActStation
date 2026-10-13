<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Http\Requests\ArticleRequest;
use App\Modules\Content\Models\Article;
use App\Modules\Content\Models\ArticleCategory;
use App\Modules\Content\Models\ArticleTag;
use App\Modules\Content\Services\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Article::query()->with(['category', 'user', 'tags']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $articles = $query->latest()->paginate(15)->withQueryString();
        $categories = ArticleCategory::query()->orderBy('name')->get();
        $filters = $request->only(['search', 'status', 'category_id']);

        return view('admin.articles.index', compact('articles', 'categories', 'filters'));
    }

    public function create(): View
    {
        $categories = ArticleCategory::query()->orderBy('name')->get();

        return view('admin.articles.create', compact('categories'));
    }

    public function store(ArticleRequest $request, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;
        $data['body'] = $sanitizer->sanitize($data['body']);

        if ($data['status'] === 'published') {
            if (empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        }

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        }

        $tagsInput = $request->input('tags');
        unset($data['tags']);

        $article = Article::create($data);
        $this->syncTags($article, is_string($tagsInput) ? $tagsInput : null);

        return redirect()
            ->route('admin.articles.index')
            ->with('success', 'Artikel berhasil ditambahkan.');
    }

    public function show(Article $article): View
    {
        $article->load(['category', 'user', 'tags']);

        return view('admin.articles.show', compact('article'));
    }

    public function edit(Article $article): View
    {
        $article->load(['category', 'tags']);
        $categories = ArticleCategory::query()->orderBy('name')->get();
        $tags = $article->tags->pluck('name')->implode(', ');

        return view('admin.articles.edit', compact('article', 'categories', 'tags'));
    }

    public function update(ArticleRequest $request, Article $article, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $request->validated();
        $data['body'] = $sanitizer->sanitize($data['body']);

        if ($data['status'] === 'published') {
            if ($article->published_at === null && empty($data['published_at'])) {
                $data['published_at'] = now();
            } elseif ($article->published_at !== null && empty($data['published_at'])) {
                $data['published_at'] = $article->published_at;
            }
        }

        if ($request->hasFile('featured_image')) {
            if ($article->featured_image && Storage::disk('public')->exists($article->featured_image)) {
                Storage::disk('public')->delete($article->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        } else {
            unset($data['featured_image']);
        }

        $tagsInput = $request->input('tags');
        unset($data['tags']);

        $article->update($data);
        $this->syncTags($article, is_string($tagsInput) ? $tagsInput : null);

        return redirect()
            ->route('admin.articles.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return redirect()
            ->route('admin.articles.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    private function syncTags(Article $article, ?string $tagsString): void
    {
        if ($tagsString === null || trim($tagsString) === '') {
            $article->tags()->sync([]);

            return;
        }

        $tagNames = array_filter(
            array_map('trim', explode(',', $tagsString)),
            fn (string $name): bool => $name !== ''
        );

        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = ArticleTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $tagIds[] = $tag->id;
        }

        $article->tags()->sync($tagIds);
    }
}
