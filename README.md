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

Add this to `app/config/auth.php`.

```php
/**
 * LDAP Configuration for ymo/l4-openldap
 */
'ldap' => array(
	'host' => 'ldap.example.com',
	'rdn' => 'ou=System,dc=example,dc=com', // rdn used by the user configured below, optional
	'username' => 'username', // optional
	'password' => 'thisisasecret', // optional

	'filter' => '(&(objectclass=posixAccount)(|(status=member)))', // optional

	'login_attribute' => 'uid', // login attributes for users
	'basedn' => 'ou=people,dc=example,dc=com', // basedn for users
	),
```