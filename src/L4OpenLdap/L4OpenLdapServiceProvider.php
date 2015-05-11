<?php

namespace Ymo\L4OpenLdap;

use Illuminate\Auth\Guard;
use Illuminate\Support\ServiceProvider;

/**
 * An OpenLDAP authentication driver for Laravel.
 *
 * @author Yuri Moens (yuri.moens@gmail.com)
 *
 */

class L4OpenLdapServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var  boolean
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('l4-openldap.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php', 'l4-openldap'
        );

        $this->app['auth']->extend('ldap', function ($app) {
            return $app->make('Ymo\L4OpenLdap\L4OpenLdapUserProvider');
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [ 'auth' ];
    }
}
