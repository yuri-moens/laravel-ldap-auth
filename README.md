# l4-openldap

An OpenLDAP authentication driver for Laravel 4.

## Installation

Add the following to your `composer.json` file.

```
require {
	"ymo/l4-openldap": "dev-master"
}
```

Run `composer update`.

Open `app/config/app.php` and add:

`Ymo\L4OpenLdap\L4OpenLdapServiceProvider`

Open `app/config/auth.php` and change the authentication driver to `ldap`.

## Configuration

Run `php artisan config:publish ymo/l4-openldap` and adjust the config file for your LDAP settings.

It can be found in `app/config/packages/ymo/l4-openldap`.