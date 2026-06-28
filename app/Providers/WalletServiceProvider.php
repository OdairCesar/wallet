<?php

namespace App\Providers;

use App\Contracts\EventPublisherInterface;
use App\Infrastructure\Events\InMemoryEventPublisher;
use App\Infrastructure\Events\KafkaEventPublisher;
use App\Projections\Projectors\WalletProjector;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InMemoryEventPublisher::class);

        $this->app->singleton(EventPublisherInterface::class, function ($app) {
            $driver = config('event_bus.driver', 'inmemory');

            if ($driver === 'kafka') {
                return $app->make(KafkaEventPublisher::class);
            }

            return $app->make(InMemoryEventPublisher::class);
        });

        $this->app->singleton(WalletProjector::class);
    }

    public function boot(): void
    {
        $publisher = $this->app->make(EventPublisherInterface::class);

        if ($publisher instanceof InMemoryEventPublisher) {
            $publisher->subscribe($this->app->make(WalletProjector::class));
        }
    }
}
