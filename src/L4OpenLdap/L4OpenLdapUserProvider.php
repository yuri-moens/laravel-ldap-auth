<?php

namespace Ymo\L4OpenLdap;

use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\UserProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;

/**
 * An OpenLDAP authentication driver for Laravel.
 *
 * @author Yuri Moens (yuri.moens@gmail.com)
 */

class L4OpenLdapUserProvider implements UserProvider
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
     * The configuration repository.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The database connection.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $dbConnection;

    /**
     * Create a new LDAP user provider.
     *
     * @param \Illuminate\Database\DatabaseManager $dbConnection
     * @param \Illuminate\Contracts\Config\Repository $config
     * @throws \Exception
     */
    public function __construct(DatabaseManager $dbConnection, Repository $config)
    {
        $this->dbConnection = $dbConnection;
        $this->config = $config;

        if (! extension_loaded('ldap')) {
            throw new Exception("PHP LDAP extension not loaded.");
        }

        if (! $this->conn = ldap_connect("ldap://" . $this->config->get('l4-openldap.host'))) {
            throw new Exception("Could not connect to LDAP host " . $this->config->get('l4-openldap.host') . ": " . ldap_error($this->conn));
        }

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, $this->config->get('l4-openldap.version'));
        ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);

        if ($this->config->get('l4-openldap.username') && $this->config->get('l4-openldap.password') && $this->config->get('l4-openldap.rdn')) {
            if (!@ldap_bind($this->conn, 'cn=' . $this->config->get('l4-openldap.username') . ',' . $this->config->get('l4-openldap.rdn'), $this->config->get('l4-openldap.password'))) {
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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        if ($entries = $this->searchLdap($identifier)) {
            if ($this->config->get('l4-openldap.use_db')) {
                $ldap_value = $entries[0][$this->config->get('l4-openldap.ldap_field')][0];
                $user = $this->dbConnection->table($this->config->get('l4-openldap.db_table'))->where($this->config->get('l4-openldap.db_field'), '=', $ldap_value)->first();

                if ($this->config->get('l4-openldap.eloquent')) {
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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        if ($entries = $this->searchLdap($identifier)) {
            $ldap_value = $entries[0][$this->config->get('l4-openldap.ldap_field')][0];
            $user = $this->dbConnection->table($this->config->get('l4-openldap.db_table'))->where($this->config->get('l4-openldap.db_field'), '=', $ldap_value)->first();

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
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string $token
     * @return void
     */
    public function updateRememberToken(UserContract $user, $token)
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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $result = @ldap_search($this->conn, $this->config->get('l4-openldap.login_attribute') . '=' . $credentials['username'] . ',' . $this->config->get('l4-openldap.basedn'), $this->config->get('l4-openldap.filter'));
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
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array
     * @return boolean
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        if ($user == null) {
            return false;
        }
        if (isset($credentials['password']) == '') {
            return false;
        }

        if (!$result = @ldap_bind($this->conn, $this->config->get('l4-openldap.login_attribute') . '=' . $credentials['username'] . ',' . $this->config->get('l4-openldap.basedn'), $credentials['password'])) {
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
        $filter = $this->config->get('l4-openldap.filter');
        if (strpos($filter, '&')) {
            $filter = substr_replace($filter, '(' . $this->config->get('l4-openldap.user_id_attribute') . '=' . $identifier . ')', strpos($filter, '&')+1, 0);
        } else {
            $filter = '(&(' . $this->config->get('l4-openldap.user_id_attribute') . '=' . $identifier . ')' . $filter . ')';
        }

        $result = @ldap_search($this->conn, $this->config->get('l4-openldap.basedn'), $filter);

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
            'id' => $entry[$this->config->get('l4-openldap.user_id_attribute')][0]
        );

        foreach ($this->config->get('l4-openldap.user_attributes') as $key => $value) {
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
        $class = '\\' . ltrim($this->config->get('l4-openldap.eloquent_user_model'), '\\');

        return new $class;
    }
}