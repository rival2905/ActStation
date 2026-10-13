<?php

declare(strict_types=1);

use App\Modules\Content\Http\Controllers\AnnouncementController;
use App\Modules\Content\Http\Controllers\ArticleCategoryController;
use App\Modules\Content\Http\Controllers\ArticleController;
use App\Modules\Content\Http\Controllers\BannerController;
use App\Modules\Content\Http\Controllers\FaqController;
use App\Modules\Content\Http\Controllers\GalleryController;
use Illuminate\Support\Facades\Route;

// Articles
Route::get('articles', [ArticleController::class, 'index'])
    ->middleware('permission:article.view')
    ->name('articles.index');
Route::get('articles/create', [ArticleController::class, 'create'])
    ->middleware('permission:article.create')
    ->name('articles.create');
Route::post('articles', [ArticleController::class, 'store'])
    ->middleware('permission:article.create')
    ->name('articles.store');
Route::get('articles/{article}', [ArticleController::class, 'show'])
    ->middleware('permission:article.view')
    ->name('articles.show');
Route::get('articles/{article}/edit', [ArticleController::class, 'edit'])
    ->middleware('permission:article.update')
    ->name('articles.edit');
Route::put('articles/{article}', [ArticleController::class, 'update'])
    ->middleware('permission:article.update')
    ->name('articles.update');
Route::delete('articles/{article}', [ArticleController::class, 'destroy'])
    ->middleware('permission:article.delete')
    ->name('articles.destroy');

// Article Categories
Route::get('article-categories', [ArticleCategoryController::class, 'index'])
    ->middleware('permission:article-category.view')
    ->name('article-categories.index');
Route::post('article-categories', [ArticleCategoryController::class, 'store'])
    ->middleware('permission:article-category.create')
    ->name('article-categories.store');
Route::put('article-categories/{articleCategory}', [ArticleCategoryController::class, 'update'])
    ->middleware('permission:article-category.update')
    ->name('article-categories.update');
Route::delete('article-categories/{articleCategory}', [ArticleCategoryController::class, 'destroy'])
    ->middleware('permission:article-category.delete')
    ->name('article-categories.destroy');

// Banners
Route::get('banners', [BannerController::class, 'index'])
    ->middleware('permission:banner.view')
    ->name('banners.index');
Route::get('banners/create', [BannerController::class, 'create'])
    ->middleware('permission:banner.create')
    ->name('banners.create');
Route::post('banners', [BannerController::class, 'store'])
    ->middleware('permission:banner.create')
    ->name('banners.store');
Route::get('banners/{banner}/edit', [BannerController::class, 'edit'])
    ->middleware('permission:banner.update')
    ->name('banners.edit');
Route::put('banners/{banner}', [BannerController::class, 'update'])
    ->middleware('permission:banner.update')
    ->name('banners.update');
Route::delete('banners/{banner}', [BannerController::class, 'destroy'])
    ->middleware('permission:banner.delete')
    ->name('banners.destroy');

// Announcements
Route::get('announcements', [AnnouncementController::class, 'index'])
    ->middleware('permission:announcement.view')
    ->name('announcements.index');
Route::get('announcements/create', [AnnouncementController::class, 'create'])
    ->middleware('permission:announcement.create')
    ->name('announcements.create');
Route::post('announcements', [AnnouncementController::class, 'store'])
    ->middleware('permission:announcement.create')
    ->name('announcements.store');
Route::get('announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])
    ->middleware('permission:announcement.update')
    ->name('announcements.edit');
Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])
    ->middleware('permission:announcement.update')
    ->name('announcements.update');
Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])
    ->middleware('permission:announcement.delete')
    ->name('announcements.destroy');

// FAQs
Route::get('faqs', [FaqController::class, 'index'])
    ->middleware('permission:faq.view')
    ->name('faqs.index');
Route::get('faqs/create', [FaqController::class, 'create'])
    ->middleware('permission:faq.create')
    ->name('faqs.create');
Route::post('faqs', [FaqController::class, 'store'])
    ->middleware('permission:faq.create')
    ->name('faqs.store');
Route::get('faqs/{faq}/edit', [FaqController::class, 'edit'])
    ->middleware('permission:faq.update')
    ->name('faqs.edit');
Route::put('faqs/{faq}', [FaqController::class, 'update'])
    ->middleware('permission:faq.update')
    ->name('faqs.update');
Route::delete('faqs/{faq}', [FaqController::class, 'destroy'])
    ->middleware('permission:faq.delete')
    ->name('faqs.destroy');

// Galleries
Route::get('galleries', [GalleryController::class, 'index'])
    ->middleware('permission:gallery.view')
    ->name('galleries.index');
Route::get('galleries/create', [GalleryController::class, 'create'])
    ->middleware('permission:gallery.create')
    ->name('galleries.create');
Route::post('galleries', [GalleryController::class, 'store'])
    ->middleware('permission:gallery.create')
    ->name('galleries.store');
Route::get('galleries/{gallery}', [GalleryController::class, 'show'])
    ->middleware('permission:gallery.view')
    ->name('galleries.show');
Route::get('galleries/{gallery}/edit', [GalleryController::class, 'edit'])
    ->middleware('permission:gallery.update')
    ->name('galleries.edit');
Route::put('galleries/{gallery}', [GalleryController::class, 'update'])
    ->middleware('permission:gallery.update')
    ->name('galleries.update');
Route::delete('galleries/{gallery}', [GalleryController::class, 'destroy'])
    ->middleware('permission:gallery.delete')
    ->name('galleries.destroy');
Route::post('galleries/{gallery}/media', [GalleryController::class, 'storeMedia'])
    ->middleware('permission:gallery.update')
    ->name('galleries.media.store');
Route::delete('galleries/{gallery}/media/{media}', [GalleryController::class, 'destroyMedia'])
    ->middleware('permission:gallery.delete')
    ->scopeBindings()
    ->name('galleries.media.destroy');
