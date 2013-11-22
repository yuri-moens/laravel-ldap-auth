<?php

namespace Ymo\L4OpenLdap;

use Illuminate\Support\ServiceProvider;

/**
 * An OpenLDAP authentication driver for Laravel 4.
 *
 * @author Yuri Moens (yuri.moens@gmail.com)
 *
 */

class L4OpenLdapServiceProvider extends ServiceProvider {

	public function boot()
	{
    	$this->package('ymo/l4-openldap');

        $this->app['auth']->extend('ldap', function($app)
        {
        	return new L4OpenLdapGuard(
            	new L4OpenLdapUserProvider(
                    	$app['config']->get('auth.ldap')
                	),
            	$app->make('session.store')
        	);
    	});
	}

	public function register()
	{

	}

	public function provides()
	{
		return array('ldap');
	}

}