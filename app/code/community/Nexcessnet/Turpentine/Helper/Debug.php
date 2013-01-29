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

class Nexcessnet_Turpentine_Helper_Debug extends Mage_Core_Helper_Abstract {
    /**
     * Dump a variable to output with <pre/> tags and disable cache flag
     *
     * @param mixed $value
     */
    public function dump( $value ) {
        Mage::register( 'turpentine_nocache_flag', true, true );
        $this->logValue( $value );
        echo '<pre>' . PHP_EOL;
        var_dump( $value );
        echo '</pre>' . PHP_EOL;
    }

    /**
     * Log message through Magento's logging facility, works like sprintf
     *
     * @param  string $message
     * @param  mixed  ...
     * @return null
     */
    public function log( $message ) {
        $args = func_get_args();
        array_shift( $args );
        Mage::log( vsprintf( 'TURPENTINE: ' . $message, $args ) );
    }

    /**
     * Log a backtrace, can pass a already generated backtrace to use
     *
     * @param  array $backTrace=null
     * @return null
     */
    public function logBackTrace( $backTrace=null ) {
        if( is_null( $backTrace ) ) {
            $backTrace = debug_backtrace();
            array_shift( $backTrace );
        }
        $btuuid = Mage::helper( 'turpentine/data' )->generateUuid();
        $this->log( 'TRACEBACK: START ** %s **', $btuuid );
        $this->log( 'TRACEBACK: URL: %s', $_SERVER['REQUEST_URI'] );
        for( $i=0; $i < count($backTrace); $i++ ) {
            $line = $backTrace[$i];
            $this->log( 'TRACEBACK: #%02d: %s:%d',
                $i, $line['file'], $line['line'] );
            $this->log( 'TRACEBACK: ==> %s%s%s(%s)',
                (is_object( @$line['object'] ) ?
                    get_class( $line['object'] ) : @$line['class'] ),
                @$line['type'],
                $line['function'],
                $this->_backtrace_formatArgs( $line['args'] ) );
        }
        $this->log( 'TRACEBACK: END ** %s **', $btuuid );
    }

    /**
     * Like var_dump to the log
     *
     * @param  mixed $value
     * @param  string $name=null
     * @return null
     */
    public function logValue( $value, $name=null ) {
        if( is_null( $name ) ) {
            $name = 'VALUE';
        }
        $this->log( '%s => %s', $name,
            $this->_backtrace_formatArgsHelper( $value ) );
    }

    /**
     * Format a list of function arguments for the backtrace
     *
     * @param  array $args
     * @return string
     */
    protected function _backtrace_formatArgs( $args ) {
        return implode( ', ',
            array_map(
                array( $this, '_backtrace_formatArgsHelper' ),
                $args
            )
        );
    }

    /**
     * Format a value for inclusion in the backtrace
     *
     * @param  mixed $arg
     * @return null
     */
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
        } elseif( is_bool( $arg ) ) {
            $value = $arg ? 'TRUE' : 'FALSE';
        }
        return $value;
    }
}
