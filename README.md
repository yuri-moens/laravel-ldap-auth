# l4-openldap

An OpenLDAP authentication driver for Laravel 5.

## Installation

Add the following to your `composer.json` file.

```
require {
	"ymo/l4-openldap": "~2.0"
}
```

Run `composer update`.

Open `config/app.php` and add:

`Ymo\L4OpenLdap\L4OpenLdapServiceProvider`

Open `config/auth.php` and change the authentication driver to `ldap`.

## Configuration

Run `php artisan publish:config ymo/l4-openldap` and adjust the config file for your LDAP settings.

It can be found in `config/l4-openldap`.