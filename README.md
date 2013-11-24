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
	'user_id_attribute' => 'uidNumber', // the attribute name containg the uid number
	'user_attributes' => array( // the ldap attributes you want to store in session (ldap_attr => array_field_name)
		'uid' => 'username', // example: this stores the ldap uid attribute as username in GenericUser
	),

	'use_db' => true, // set to true if you want to retrieve more information from a database, the next 4 variables are required if this is set to true
	'ldap_field' => 'uid', // the LDAP field we want to compare to the db_field to find our user
	'db_connection' => DB::connection(), // the DB connection
	'db_table' => 'users', // the table where we should look for users
	'db_field' => 'user_name', // the DB field we want to compare to the ldap_field to find our user
),
```