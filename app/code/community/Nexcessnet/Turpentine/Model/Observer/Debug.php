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

class Nexcessnet_Turpentine_Model_Observer_Debug extends Varien_Event_Observer {
    public function eventDebug( $eventObject ) {
        $this->log( 'EVENT: %s', $eventObject->getEvent()->getName() );
    }

    public function log( $message ) {
        $args = func_get_args();
        array_shift( $args );
        Mage::log( vsprintf( $message, $args ) );
    }

    public function backtrace() {
        $tb = debug_backtrace();
        array_shift( $tb );
        $this->log( 'TRACEBACK: START: ' . $_SERVER['REQUEST_URI'] );
        for( $i=0; $i < count($tb); $i++ ) {
            $line = $tb[$i];
            $this->log( 'TRACEBACK: #%02d: %s:%d',
                $i, $line['file'], $line['line'] );
            $this->log( 'TRACEBACK: ==> %s%s%s(%s)',
                (is_object( @$line['object'] ) ? get_class( $line['object'] ) : @$line['class'] ),
                @$line['type'],
                $line['function'],
                $this->_backtrace_formatArgs( $line['args'] ) );
        }
        $this->log( 'TRACEBACK: END: ' . $_SERVER['REQUEST_URI'] );
    }

    protected function _backtrace_formatArgs( $args ) {
        return implode( ', ',
            array_map(
                array( $this, '_backtrace_formatArgsHelper' ),
                $args
            )
        );
    }

    protected function _backtrace_formatArgsHelper( $arg ) {
        $value = $arg;
        if( is_object( $arg ) ) {
            $value = sprintf( 'OBJECT(%s)', get_class( $arg ) );
        } elseif( is_resource( $arg ) ) {
            $value = 'RESOURCE';
        } elseif( is_array( $arg ) ) {
            $value = 'ARRAY[%s](%s)';
            $c = array();
            foreach( $arg as $k => $v ) {
                $c[] = sprintf( '%s => %s', $k,
                    $this->_backtrace_formatArgsHelper( $v ) );
            }
            $value = sprintf( $value, count( $arg ), implode( ', ', $c ) );
        } elseif( is_string( $arg ) ) {
            $value = sprintf( '\'%s\'', $arg );
        }
        return $value;
    }
}
