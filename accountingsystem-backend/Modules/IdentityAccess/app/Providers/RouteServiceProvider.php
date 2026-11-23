<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'IdentityAccess';

    public function map(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(module_path($this->name, 'routes/api.php'));
    }
}
