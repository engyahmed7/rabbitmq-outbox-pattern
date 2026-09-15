<?php

namespace App\Providers;

use App\Contracts\MessagePublisher;
use App\RabbitMQ\FakeMessagePublisher;
use App\RabbitMQ\RabbitMQPublisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MessagePublisher::class, function ($app) {
            if (config('rabbitmq.driver') === 'fake') {
                return $app->make(FakeMessagePublisher::class);
            }

            return $app->make(RabbitMQPublisher::class);
        });

        $this->app->singleton(FakeMessagePublisher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
