<?php

namespace App\Providers;

use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // if (config('app.env') !== 'local') {
        //     URL::forceScheme('https');
        // }
        Model::preventLazyLoading(!app()->isProduction());

        Blade::directive('permission', function (string $expression) {
            return "<?php if(app(\App\Services\PermissionService::class)->can(\Illuminate\Support\Facades\Auth::id(), {$expression})): ?>";
        });

        Blade::directive('endpermission', function () {
            return '<?php endif; ?>';
        });
    }
}
