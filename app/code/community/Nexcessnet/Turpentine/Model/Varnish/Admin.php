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

    protected $_configurator = null;

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
        $cfgr = $this->getConfigurator();
        $pattern = $cfgr->getBaseUrlPathRegex() . $subPattern;
        $result = array();
        foreach( $cfgr->getSockets() as $socket ) {
            $socketName = sprintf( '%s:%d', $socket->getHost(), $socket->getPort() );
            try {
                $socket->ban_url( $pattern );
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
        $result = array();
        foreach( $this->getConfigurator()->getSockets() as $socket ) {
            $socketName = sprintf( '%s:%d', $socket->getHost(), $socket->getPort() );
            try {
                $socket->ban( 'obj.http.Content-type', '~', $contentType );
            } catch( Mage_Core_Exception $e ) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Generate and apply the config to the Varnish instances
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function applyConfig() {
        $result = array();
        $cfgr = $this->getConfigurator();
        $vcl = $cfgr->generate();
        $vclname = hash( 'sha256', microtime() );
        foreach( $cfgr->getSockets() as $socket ) {
            $socketName = sprintf( '%s:%d', $socket->getHost(), $socket->getPort() );
            try {
                $socket->vcl_inline( $vclname, $vcl );
                sleep(1);
                $socket->vcl_use( $vclname );
            } catch( Mage_Core_Exception $e ) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Get the appropriate configurator based on the specified Varnish version
     * in the Magento config
     *
     * @param  string $version=null provide version string instead of pulling
     *                              from config
     * @return Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract
     */
    public function getConfigurator( $version=null ) {
        if( is_null( $this->_configurator ) ) {
            if( is_null( $version ) ) {
                $version = Mage::getStoreConfig(
                    'turpentine_servers/servers/version' );
            }
            switch( $version ) {
                case '2.1':
                    $this->_configurator = Mage::getModel(
                        'turpentine/varnish_configurator_version2' );
                    break;
                case '3.0':
                    $this->_configurator = Mage::getModel(
                        'turpentine/varnish_configurator_version3' );
                    break;
                case 'auto':
                default:
                    $sockets = Mage::getModel(
                        'turpentine/varnish_configurator_version3' )->getSockets();
                    foreach( $sockets as $socket ) {
                        return $this->getConfigurator( $socket->getVersion() );
                    }
                    break;
            }

        }
        return $this->_configurator;
    }
}