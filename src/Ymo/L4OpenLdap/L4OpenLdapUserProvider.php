<?php

namespace Ymo\L4OpenLdap;

use Config;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\UserProviderInterface;
use Illuminate\Database\Connection;

/**
 * An OpenLDAP authentication driver for Laravel 4.
 *
 * @author Yuri Moens (yuri.moens@gmail.com)
 *
 */

class L4OpenLdapUserProvider implements UserProviderInterface
{
    /**
     * The Eloquent user model.
     *
     * @var  string
     */
    protected $model;

    /**
     * The LDAP connection.
     *
     * @var ldap link
     */
    protected $conn;

    /**
     * The active database connection.
     *
     * @param  \Illuminate\Database\Connection
     */
    protected $db_conn;

    /**
     * Create a new LDAP user provider.
     * 
     * @param 
     */
    public function __construct(Connection $db_conn)
    {
        $this->db_conn = $db_conn;

        if (! extension_loaded('ldap')) {
            throw new Exception("PHP LDAP extension not loaded.");
        }

        if (! $this->conn = ldap_connect("ldap://" . Config::get('l4-openldap::host'))) {
            throw new Exception("Could not connect to LDAP host " . Config::get('l4-openldap::host') . ": " . ldap_error($this->conn));
        }

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, Config::get('l4-openldap::version'));
        ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);

        if (Config::get('l4-openldap::username') && Config::get('l4-openldap::password') && Config::get('l4-openldap::rdn')) {
            if (!@ldap_bind($this->conn, 'cn=' . Config::get('l4-openldap::username') . ',' . Config::get('l4-openldap::rdn'), Config::get('l4-openldap::password'))) {
                throw new Exception('Could not bind to AD: ' . ldap_error($this->conn));
            }
        } else {
            if (!@ldap_bind($this->conn)) {
                throw new Exception('Could not bind to AD: ' . ldap_error($this->conn));
            }
        }
    }

    /**
     * Clean up the LDAP connection.
     */
    public function __destruct()
    {
        if (! is_null($this->conn)) {
            ldap_unbind($this->conn);
        }
    }

    /**
     * Retrieve a user by their unique identifier.
     * 
     * @param  mixed $identifier
     * @return \Illuminate\Auth\UserInterface|null
     */
    public function retrieveById($identifier)
    {
        if ($entries = $this->searchLdap($identifier)) {
            if (Config::get('l4-openldap::use_db')) {
                $ldap_value = $entries[0][Config::get('l4-openldap::ldap_field')][0];
                $user = $this->db_conn->table(Config::get('l4-openldap::db_table'))->where(Config::get('l4-openldap::db_field'), '=', $ldap_value)->first();

                if (Config::get('l4-openldap::eloquent')) {
                    return $this->createModel()->newQuery()->find($user->id);
                } else {
                    return new GenericUser(get_object_vars($user));
                }
            } else {
                return $this->createGenericUserFromLdap($entries[0]);
            }
        }        
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     * 
     * @param  mixed $identifier
     * @param  string $token
     * @return \Illuminate\Auth\UserInterface|null
     */
    public function retrieveByToken($identifier, $token)
    {
        if ($entries = $this->searchLdap($identifier)) {
                $ldap_value = $entries[0][Config::get('l4-openldap::ldap_field')][0];
                $user = $this->db_conn->table(Config::get('l4-openldap::db_table'))->where(Config::get('l4-openldap::db_field'), '=', $ldap_value)->first();

                $model = $this->createModel();

                return $model->newQuery()
                    ->where('id', $user->id)
                    ->where($model->getRememberTokenName(), $token)
                    ->first();
        } 
    }

    /**
     * Update the "remember me" token for the given user in storage.
     * 
     * @param  \Illuminate\Auth\UserInterface $user
     * @param  string $token
     * @return void
     */
    public function updateRememberToken(UserInterface $user, $token)
    {
        if (! $user instanceof GenericUser) {
            $user->setAttribute($user->getRememberTokenName(), $token);

            $user->save();
        }
    }

    /**
     * Retrieve a user by the given credentials.
     * 
     * @param  array  $credentials
     * @return \Illuminate\Auth\UserInterface|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $result = @ldap_search($this->conn, Config::get('l4-openldap::login_attribute') . '=' . $credentials['username'] . ',' . Config::get('l4-openldap::basedn'), Config::get('l4-openldap::filter'));
        if ($result == false) {
            return;
        }

        $entries = ldap_get_entries($this->conn, $result);
        if ($entries['count'] == 0 || $entries['count'] > 1) {
            return;
        }

        $this->model = $this->createGenericUserFromLdap($entries[0]);

        return $this->model;
    }

    /**
     * Validate a user against the given credentials.
     * 
     * @param  \Illuminate\Auth\UserInterface  $user
     * @param  array
     * @return boolean
     */
    public function validateCredentials(UserInterface $user, array $credentials)
    {
        if ($user == null) {
            return false;
        }
        if (isset($credentials['password']) == '') {
            return false;
        }

        if (!$result = @ldap_bind($this->conn, Config::get('l4-openldap::login_attribute') . '=' . $credentials['username'] . ',' . Config::get('l4-openldap::basedn'), $credentials['password'])) {
            return false;
        }

        return true;
    }

    /**
     * Search the LDAP server for entries that match the specified identifier.
     * 
     * @param  mixed $identifier
     * @return array|null
     */
    private function searchLdap($identifier)
    {
        $filter = Config::get('l4-openldap::filter');
        if (strpos($filter, '&')) {
            $filter = substr_replace($filter, '(' . Config::get('l4-openldap::user_id_attribute') . '=' . $identifier . ')', strpos($filter, '&')+1, 0);
        } else {
            $filter = '(&(' . Config::get('l4-openldap::user_id_attribute') . '=' . $identifier . ')' . $filter . ')';
        }

        $result = @ldap_search($this->conn, Config::get('l4-openldap::basedn'), $filter);

        if ($result == false) {
            return;
        }

        $entries = ldap_get_entries($this->conn, $result);
        if ($entries['count'] == 0 || $entries['count'] > 1) {
            return;
        }

        return $entries;
    }

    /**
     * Create a GenericUser from the specified LDAP entry.
     * 
     * @param  array $entry
     * @return \Illuminate\Auth\GenericUser
     */
    private function createGenericUserFromLdap($entry)
    {
        $parameters = array (
            'id' => $entry[Config::get('l4-openldap::user_id_attribute')][0]
        );

        foreach (Config::get('l4-openldap::user_attributes') as $key => $value) {
            $parameters[$value] = $entry[$key][0];
        }

        return new GenericUser($parameters);
    }

    /**
     * Create a new model instance.
     * 
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function createModel()
    {
        $class = '\\' . ltrim(Config::get('l4-openldap::eloquent_user_model'), '\\');

        return new $class;
    }
}
