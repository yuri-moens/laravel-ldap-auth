<?php

namespace Ymo\L4OpenLdap;

use Illuminate\Auth\UserProviderInterface;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\GenericUser;

/**
 * An OpenLDAP authentication driver for Laravel 4.
 *
 * @author Yuri Moens (yuri.moens@gmail.com)
 *
 */

class L4OpenLdapUserProvider implements UserProviderInterface {

	/**
	 * The Eloquent user model
	 * @var GenericUser
	 */
	protected $model;

	public function __construct($config)
	{
		$this->config = $config;

		if (!$this->conn = ldap_connect("ldap://{$this->config['host']}"))
		{
			throw new Exception("Could not connect to LDAP host {$this->config['host']}: " . ldap_error($this->conn));
		}

		if ($this->config['username'] && $this->config['password'] && $this->config['rdn'])
		{
			if (!@ldap_bind($this->conn, 'cn=' . $this->config['username'] . ',' . $this->config['rdn'], $this->config['password']))
			{
				throw new Exception('Could not bind to AD: ' . ldap_error($this->conn));
			}
		} else {
			if (!@ldap_bind($this->conn))
			{
				throw new Exception('Could not bind to AD: ' . ldap_error($this->conn));
			}
		}
	}

	public function __destruct()
	{
		if (!is_null($this->conn))
		{
			ldap_unbind($this->conn);
		}
	}

	public function retrieveByID($identifier)
	{
		$result = @ldap_read($this->conn, $identifier, '(objectclass=*)', $this->config['attributes']);
		if ($result == false)
			return null;

		$entries = ldap_get_entries($this->conn, $result);
		if ($entries['count'] == 0 || $entries['count'] > 1)
			return null;

		$this->model = $this->createGenericUser($entries[0]);
		return $this->model;
	}

	public function retrieveByCredentials(array $credentials)
	{
		$result = @ldap_search($this->conn, $this->config['login_attribute'] . '=' . $credentials['username'] . ',' . $this->config['basedn'], $this->config['filter']);
		if ($result == false)
			return null;

		$entries = ldap_get_entries($this->conn, $result);
		if ($entries['count'] == 0 || $entries['count'] > 1)
			return null;

		$this->model = $this->createGenericUser($entries[0]);
		return $this->model;
	}

	public function validateCredentials(UserInterface $user, array $credentials)
	{
		if ($user == null)
			return false;
		if (isset($credentials['password']) == '')
			return false;

		if (!$result = @ldap_bind($this->conn, $this->config['login_attribute'] . '=' . $credentials['username'] . ',' . $this->config['basedn'], $credentials['password']))
			return false;

		return true;
	}

	public function createGenericUser($entry)
	{
		return new GenericUser([
			'id' => $entry['uid'][0],
		]);
	}

}