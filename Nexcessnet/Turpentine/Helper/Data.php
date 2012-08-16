<?php

/** 
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */ 

class Nexcessnet_Turpentine_Helper_Data extends Mage_Core_Helper_Abstract {
    const NO_CACHE_COOKIE   = 'varnish_nocache';
    const ADMIN_COOKIE      = 'adminhtml';

    /**
     * Get the name of the varnish no cache cookie
     *
     * @return string
     */
    public function getNoCacheCookieName() {
        return self::NO_CACHE_COOKIE;
    }

    /**
     * Get the name of the admin cookie
     *
     * @return string
     */
    public function getAdminCookieName() {
        return self::ADMIN_COOKIE;
    }

    /**
     * Get the actual Varnish no cache cookie object
     *
     * @return Mage_Core_Model_Cookie
     */
    public function getNoCacheCookie() {
        return Mage::getModel( 'core/cookie' )->get(
            $this->getNoCacheCookieName() );
    }
}