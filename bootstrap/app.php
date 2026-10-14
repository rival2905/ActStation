<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInternalRole;
use App\Http\Middleware\EnsureOrganizerEventOwner;
use App\Modules\Community\Http\Middleware\EnsurePanelAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'auth', 'internal'])
                ->prefix('admin')
                ->name('admin.')
                ->group(function (): void {
                    require app_path('Modules/MasterData/Routes/admin.php');
                    require app_path('Modules/Settings/Routes/admin.php');
                    require app_path('Modules/Content/Routes/admin.php');
                    require app_path('Modules/Community/Routes/admin.php');
                    require app_path('Modules/Event/Routes/admin.php');
                    require app_path('Modules/Ticketing/Routes/admin.php');
                    require app_path('Modules/Payment/Routes/admin.php');
                    require app_path('Modules/Marketplace/Routes/admin.php');
                    require app_path('Modules/Report/Routes/admin.php');
                    require app_path('Modules/Communication/Routes/admin.php');
                });

            Route::middleware('web')
                ->group(app_path('Modules/Community/Routes/web.php'));

            Route::middleware('web')
                ->group(app_path('Modules/Communication/Routes/web.php'));

            Route::middleware('web')
                ->prefix('panel')
                ->name('panel.')
                ->group(function (): void {
                    require app_path('Modules/Community/Routes/panel.php');
                    require app_path('Modules/MasterData/Routes/panel.php');
                    if (file_exists(app_path('Modules/Event/Routes/panel.php'))) {
                        require app_path('Modules/Event/Routes/panel.php');
                    }
                });
        },
    )
    ->withCommands([
        __DIR__.'/../app/Modules/Community/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'internal' => EnsureInternalRole::class,
            'organizer.event' => EnsureOrganizerEventOwner::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'EnsurePanelAccess' => EnsurePanelAccess::class,
            'panel.access' => EnsurePanelAccess::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('panel/*') ? (Route::has('panel.login') ? route('panel.login') : route('admin.login')) : route('admin.login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->getPanelHomeRoute() ?? route('landing'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('admin/events/*/scheduler/api/*') || $request->is('panel/organizer/events/*/scheduler/api/*') || $request->is('admin/events/*/tournament/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->is('admin/events/*/scheduler/api/*') || $request->is('panel/organizer/events/*/scheduler/api/*') || $request->is('admin/events/*/tournament/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['error' => [$e->getMessage()]],
                ], 422);
            }
        });

        $exceptions->render(function (LogicException $e, Request $request) {
            if ($request->is('admin/events/*/scheduler/api/*') || $request->is('panel/organizer/events/*/scheduler/api/*') || $request->is('admin/events/*/tournament/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['error' => [$e->getMessage()]],
                ], 422);
            }
        });
    })->create();
