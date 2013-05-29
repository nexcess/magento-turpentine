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

class Nexcessnet_Turpentine_Helper_Varnish extends Mage_Core_Helper_Abstract {

    const MAGE_CACHE_NAME           = 'turpentine_pages';

    /**
     * Get whether Varnish caching is enabled or not
     *
     * @return bool
     */
    public function getVarnishEnabled() {
        return Mage::app()->useCache( $this->getMageCacheName() );
    }

    /**
     * Get whether Varnish debugging is enabled or not
     *
     * @return bool
     */
    public function getVarnishDebugEnabled() {
        return (bool)Mage::getStoreConfig(
            'turpentine_varnish/general/varnish_debug' );
    }

    /**
     * Check if the request passed through Varnish (has the correct secret
     * handshake header)
     *
     * @return boolean
     */
    public function isRequestFromVarnish() {
        return $this->getSecretHandshake() ==
            Mage::app()->getRequest()->getHeader( 'X-Turpentine-Secret-Handshake' );
    }

    /**
     * Check if Varnish should be used for this request
     *
     * @return bool
     */
    public function shouldResponseUseVarnish() {
        return $this->getVarnishEnabled() && $this->isRequestFromVarnish();
    }

    /**
     * Get the secret handshake value
     *
     * @return string
     */
    public function getSecretHandshake() {
        return '1';
        /**
         * If we use the below code for the secret handshake, it will make the
         * secret handshake not-forgable but will break multistore setups that
         * don't share the same encryption key, which it turns out is a common
         * use case, even though it is kind of a hack and really shouldn't be
         * done. Fortunately forging the secret handshake shouldn't really be
         * a security vulnerability since it won't show any information that
         * wouldn't be available anyways (like debug headers), it would just
         * cause ESI injection despite the request not passing through Varnish
         * for ESI parsing/handling.
         */
        // return Mage::helper( 'turpentine/data' )->secureHash(
        //     Mage::getStoreConfig( 'turpentine_varnish/servers/auth_key' ) );
    }

    /**
     * Get a Varnish management socket
     *
     * @param  string $host           [description]
     * @param  string|int $port           [description]
     * @param  string $secretKey=null [description]
     * @param  string $version=null   [description]
     * @return Nexcessnet_Turpentine_Model_Varnish_Admin_Socket
     */
    public function getSocket( $host, $port, $secretKey=null, $version=null ) {
        $socket = Mage::getModel( 'turpentine/varnish_admin_socket',
            array( 'host' => $host, 'port' => $port ) );
        if( $secretKey ) {
            $socket->setAuthSecret( $secretKey );
        }
        if( $version ) {
            $socket->setVersion( $version );
        }
        return $socket;
    }

    /**
     * Get management sockets for all the configured Varnish servers
     *
     * @return array
     */
    public function getSockets() {
        $sockets = array();
        $servers = Mage::helper( 'turpentine/data' )->cleanExplode( PHP_EOL,
            Mage::getStoreConfig( 'turpentine_varnish/servers/server_list' ) );
        $key = str_replace( '\n', PHP_EOL,
            Mage::getStoreConfig( 'turpentine_varnish/servers/auth_key' ) );
        $version = Mage::getStoreConfig( 'turpentine_varnish/servers/version' );
        if( $version == 'auto' ) {
            $version = null;
        }
        foreach( $servers as $server ) {
            $parts = explode( ':', $server );
            $sockets[] = $this->getSocket( $parts[0], $parts[1], $key, $version );
        }
        return $sockets;
    }

    /**
     * Get the cache type Magento uses
     *
     * @return string
     */
    public function getMageCacheName() {
        return self::MAGE_CACHE_NAME;
    }

    /**
     * Get the configured default object TTL
     *
     * @return string
     */
    public function getDefaultTtl() {
        return Mage::getStoreConfig( 'turpentine_vcl/ttls/default_ttl' );
    }

    /**
     * Check if the product list toolbar fix is enabled and we're not in the
     * admin section
     *
     * @return bool
     */
    public function shouldFixProductListToolbar() {
        return Mage::helper( 'turpentine/data' )->useProductListToolbarFix() &&
            Mage::app()->getStore()->getCode() !== 'admin';
    }

    /**
     * Check if the Varnish bypass is enabled
     *
     * @return boolean
     */
    public function isBypassEnabled() {
        $cookieName     = Mage::helper( 'turpentine' )->getBypassCookieName();
        $cookieValue    = (bool)Mage::getModel( 'core/cookie' )->get($cookieName);

        return $cookieValue;
    }

    /**
     * Check if the notification about the Varnish bypass must be displayed
     *
     * @return boolean
     */
    public function shouldDisplayNotice() {
        return $this->getVarnishEnabled() && $this->isBypassEnabled();
    }
}
