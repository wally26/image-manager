<?php
namespace Rodler\ImageManger;

use Illuminate\Support\ServiceProvider;

class ImageManagerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [__DIR__.'/assets' => public_path('vendor/rodler/image-manager')]
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ImageManager\Facades\ImageManager::class, function($app) {
            return new ImageManager;
        });

        $this->app['image-manager'] = $this->app->make(ImageManager\Facades\ImageManager::class);

        $this->app->booting(function() {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('ImageManager', 'ImageManager\Facades\ImageManager');
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

}