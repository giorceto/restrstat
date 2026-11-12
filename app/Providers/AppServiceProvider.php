<?php

namespace App\Providers;

use App\Services\Samo\SamoSource;
use App\Services\Samo\SqlSamoSource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SamoSource::class, fn () => new SqlSamoSource);
    }

    public function boot(): void
    {
        // зад обратното прокси на /restrstat: всички адреси се строят от APP_URL (с /restrstat и https)
        $url = (string) config('app.url');
        if (! $this->app->runningUnitTests() && $url !== '') {
            URL::forceRootUrl($url);
            if (str_starts_with($url, 'https://')) {
                URL::forceScheme('https');
            }
        }

        Blade::directive('eur', fn ($e) => "<?php echo \\App\\Support\\Fmt::eur($e); ?>");
        Blade::directive('num', fn ($e) => "<?php echo \\App\\Support\\Fmt::num($e); ?>");
        Blade::directive('pct', fn ($e) => "<?php echo \\App\\Support\\Fmt::pct($e); ?>");
        Blade::directive('delta', fn ($e) => "<?php echo \\App\\Support\\Fmt::delta($e); ?>");
    }
}
