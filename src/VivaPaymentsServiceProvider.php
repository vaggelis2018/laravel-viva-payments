<?php

namespace Sebdesign\VivaPayments;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;

class VivaPaymentsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/services.php', 'services'
        );

        $this->app->singleton(Client::class, function ($app) {
            return new Client($this->bootGuzzleClient($app));
        });
    }

    /**
     * Instantiate the Guzzlehttp client.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     * @return GuzzleHttp\Client
     */
    protected function bootGuzzleClient($app)
    {
        $config = $app['config']->get('services.viva');

        return new GuzzleClient([
            'base_uri' => $this->getUrl($config['environment']),
            \GuzzleHttp\RequestOptions::AUTH => [
                $config['merchant_id'],
                $config['api_key'],
            ],
        ]);
    }

    /**
     * Get the URL based on the environment.
     *
     * @param  string $environment
     * @return string
     */
    protected function getUrl($environment)
    {
        if ($environment === 'production') {
            return Client::PRODUCTION_URL;
        }

        if ($environment === 'demo') {
            return Client::DEMO_URL;
        }

        throw new \InvalidArgumentException('The Viva Payments environment must be demo or production.');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Client::class];
    }
}
