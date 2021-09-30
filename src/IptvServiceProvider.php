<?php
namespace Dadaodata\Iptv;

use Illuminate\Support\ServiceProvider;
use Dadaodata\Iptv\Services\IptvService;

class IptvServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        //配置文件
        $this->publishes([
            __DIR__.'/../config/iptv.php' => config_path('iptv.php'),
        ]);

        //发布路由
        $this->loadRoutesFrom(__DIR__.'/../routes/iptv.php');
    }

    /**
     * 注册服务提供者。
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(IptvService::class, function () {
            return new IptvService();
        });
        // Register a class in the service container
        /*
        $this->app->bind('calculator', function ($app) {
            return new Calculator();
        });
        */
    }
}
