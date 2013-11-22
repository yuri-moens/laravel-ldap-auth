<?php

namespace Ymo\L4OpenLdap;

use Illuminate\Auth\Guard;

/**
 * An OpenLDAP authentication driver for Laravel 4.
 *
 * @author Yuri Moens (yuri.moens@gmail.com)
 *
 */

 class L4OpenLdapGuard extends Guard
 {
 	public function admin()
 	{
 		if ($this->check() && $this->user())
 			return $this->user()->type == 0;

 		return false;
 	}
 }