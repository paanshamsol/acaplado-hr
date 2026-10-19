<?php

namespace App\Providers;

use App\Observers\AuditObserver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('files', function () {
            return new class extends Filesystem
            {
                public function replace($path, $content, $mode = null)
                {
                    clearstatcache(true, $path);

                    $path = realpath($path) ?: $path;

                    $tempPath = tempnam(dirname($path), basename($path));

                    if (! is_null($mode)) {
                        @chmod($tempPath, $mode);
                    } else {
                        @chmod($tempPath, 0777 - umask());
                    }

                    file_put_contents($tempPath, $content);

                    if (! @rename($tempPath, $path)) {
                        usleep(15000);

                        if (! @rename($tempPath, $path)) {
                            @file_put_contents($path, $content, LOCK_EX);
                            @unlink($tempPath);
                        }
                    }
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(7)->mixedCase()->symbols());

        foreach (AuditObserver::models() as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
