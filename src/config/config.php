<?php

/**
 * LDAP configuration for ymo/l4-openldap
 */

return array(

    'host' => 'ldap.example.com',
    'rdn' => 'ou=System,dc=example,dc=com', // rdn used by the user configured below, optional
    'username' => 'username', // optional
    'password' => 'thisisasecret', // optional
    'version'  => '3',   // LDAP protocol version (2 or 3)

    'filter' => '(&(objectclass=posixAccount)(|(status=member)))', // optional

    'login_attribute' => 'uid', // login attributes for users
    'basedn' => 'ou=people,dc=example,dc=com', // basedn for users
    'user_id_attribute' => 'uidNumber', // the attribute name containg the uid number
    'user_attributes' => array( // the ldap attributes you want to store in session (ldap_attr => array_field_name)
        'uid' => 'username', // example: this stores the ldap uid attribute as username in GenericUser
    ),

    'use_db' => true, // set to true if you want to retrieve more information from a database, the next 4 variables are required if this is set to true
    'ldap_field' => 'uid', // the LDAP field we want to compare to the db_field to find our user
    'db_table' => 'users', // the table where we should look for users
    'db_field' => 'user_name', // the DB field we want to compare to the ldap_field to find our user
    'eloquent' => true, // set to true if you want to return an Eloquent user instead of a GenericUser object
    'eloquent_user_model' => 'User', // name of the User model

);
