#!/usr/bin/php -f
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

require_once( dirname( $_SERVER['argv'][0] ) . '/abstract.php' );

class Turpentine_Shell_Varnishadm extends Mage_Shell_Abstract {
    /**
     * Get the real cleaned argv
     * 
     * @return array
     */
    protected function _parseArgs() {
        $this->_args = array_slice(
            array_filter( $_SERVER['argv'],
                create_function( '$e',
                    'return $e != \'--\';' ) ),
            1 );
        return $this;
    }
    
    protected function _write() {
        $args = func_get_args();
        return call_user_func_array( 'printf', $args );
    }
    
    /**
     * Run script
     * 
     * @return null
     */
    public function run() {
        $command = str_replace( '.', '_', $this->_args[0] );
        $params = array_slice( $this->_args, 1 );
        foreach( Mage::helper( 'turpentine/varnish' )->getSockets() as $socket ) {
            $response = call_user_func_array( array( $socket, $command ), $params );
            $this->_write( "=== Result from server [%s]: %d ===\n%s\n",
                $socket->getConnectionString(), $response['code'],
                $response['text'] );
        }
    }
    
    /**
     * Get the usage string
     * 
     * @return string
     */
    public function usageHelp() {
        return <<<USAGE
Usage:  php -f varnishadm.php -- <command> [args]
        php -f varnishadm.php -- ban.url /category/product
    
    Do the 'help' command to see the list of available commands

USAGE;
    }
}

$shell = new Turpentine_Shell_Varnishadm();
$shell->run();
