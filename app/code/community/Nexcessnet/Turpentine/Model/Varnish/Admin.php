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

class Nexcessnet_Turpentine_Model_Varnish_Admin {

    /**
     * Flush all Magento URLs in Varnish cache
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function flushAll() {
        return $this->flushUrl( '.*' );
    }

    /**
     * Flush all Magento URLs matching the given (relative) regex
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @param  string $pattern regex to match against URLs
     * @return bool
     */
    public function flushUrl( $subPattern ) {
        $result = array();
        foreach( Mage::helper( 'turpentine/varnish' )->getSockets() as $socket ) {
            $socketName = $socket->getConnectionString();
            try {
                // We don't use "ban_url" here, because we want to do lurker friendly bans.
                // Lurker friendly bans get cleaned up, so they don't slow down Varnish.
                $socket->ban( 'obj.http.X-Varnish-URL', '~', $subPattern );
            } catch( Mage_Core_Exception $e ) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Flush according to Varnish expression
     *
     * @param  mixed ...
     * @return array
     */
    public function flushExpression() {
        $args = func_get_args();
        $result = array();
        foreach( Mage::helper( 'turpentine/varnish' )->getSockets() as $socket ) {
            $socketName = $socket->getConnectionString();
            try {
                call_user_func_array( array( $socket, 'ban' ), $args );
            } catch( Mage_Core_Exception $e ) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Flush all cached objects with the given content type
     *
     * @param  string $contentType
     * @return array
     */
    public function flushContentType( $contentType ) {
        return $this->flushExpression(
            'obj.http.Content-Type', '~', $contentType );
    }

    /**
     * Generate and apply the config to the Varnish instances
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function applyConfig() {
        $result = array();
        foreach( Mage::helper( 'turpentine/varnish' )->getSockets() as $socket ) {
            $cfgr = Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract::getFromSocket( $socket );
            $socketName = $socket->getConnectionString();
            if( is_null( $cfgr ) ) {
                $result[$socketName] = 'Failed to load configurator';
            } else {
                $vcl = $cfgr->generate();
                $vclName = Mage::helper( 'turpentine/data' )
                    ->secureHash( microtime() );
                try {
                    $socket->vcl_inline( $vclName, $vcl );
                    sleep( 1 ); //this is probably not really needed
                    $socket->vcl_use( $vclName );
                } catch( Mage_Core_Exception $e ) {
                    $result[$socketName] = $e->getMessage();
                    continue;
                }
                $result[$socketName] = true;
            }
        }
        return $result;
    }

    /**
     * Get a configurator based on the first socket in the server list
     *
     * @return Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract
     */
    public function getConfigurator() {
        $sockets = Mage::helper( 'turpentine/varnish' )->getSockets();
        $cfgr = Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract::getFromSocket( $sockets[0] );
        return $cfgr;
    }
}
