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
     * Logs errors
     *
     * @param $message
     * @return string
     */
    public function logError( $message )
    {
        if ( func_num_args() > 1 ) {
            $message = $this->_prepareLogMessage( func_get_args() );
        }

        return $this->_log( Zend_Log::ERR, $message );
    }

    /**
     * Logs warnings
     *
     * @param $message
     * @return string
     */
    public function logWarn( $message )
    {
        if ( func_num_args() > 1 ) {
            $message = $this->_prepareLogMessage( func_get_args() );
        }

        return $this->_log( Zend_Log::WARN, $message );
    }

    /**
     * Logs notices
     *
     * @param $message
     * @return string
     */
    public function logNotice( $message )
    {
        if ( func_num_args() > 1 ) {
            $message = $this->_prepareLogMessage( func_get_args() );
        }

        return $this->_log( Zend_Log::NOTICE, $message );
    }

    /**
     * Logs info.
     *
     * @param $message
     * @return string
     */
    public function logInfo( $message )
    {
        if ( func_num_args() > 1 ) {
            $message = $this->_prepareLogMessage( func_get_args() );
        }

        return $this->_log( Zend_Log::INFO, $message );
    }

    /**
     * Logs debug.
     *
     * @param $message
     * @return string
     */
    public function logDebug( $message )
    {
        if( ! Mage::helper( 'turpentine/varnish' )->getVarnishDebugEnabled() ) {
            return;
        }

        if ( func_num_args() > 1 ) {
            $message = $this->_prepareLogMessage( func_get_args() );
        }

        return $this->_log( Zend_Log::DEBUG, $message );
    }

    /**
     * Prepares advanced log message.
     *
     * @param array $args
     * @return string
     */
    protected function _prepareLogMessage( array $args )
    {
        $pattern = $args[0];
        $substitutes = array_slice( $args, 1 );

        if ( ! $this->_validatePattern( $pattern, $substitutes ) ) {
            return $pattern;
        }

        return vsprintf( $pattern, $substitutes );
    }

    /**
     * Validates string and attributes for substitution as per sprintf function.
     *
     * NOTE: this could be implemented as shown at
     * http://stackoverflow.com/questions/2053664/how-to-check-that-vsprintf-has-the-correct-number-of-arguments-before-running
     * although IMHO it's too time consuming to validate the patterns.
     *
     * @param string $pattern
     * @param array $arguments
     * @return bool
     */
    protected function _validatePattern( $pattern, $arguments )
    {
        return true;
    }

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
        return call_user_func_array( array( $this, 'logDebug' ), $args );
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
     * Log a message through Magento's logging facility
     *
     * @param  int $level
     * @param  string $message
     * @return string
     */
    protected function _log( $level, $message ) {
        $message = 'TURPENTINE: ' . $message;
        Mage::log( $message, $level, $this->_getLogFileName() );
        return $message;
    }

	/**
	 * Get the name of the log file to use
	 * @return string
	 */
	protected function _getLogFileName() {
		if ( $this->useCustomLogFile() ) {
			return $this->getCustomLogFileName();
		}
		return '';
	}

	/**
	 * Check if custom log file should be used
	 * @return bool
	 */
	public function useCustomLogFile() {
		return Mage::getStoreConfigFlag(
			'turpentine_varnish/logging/use_custom_log_file' );
	}

	/**
	 * Get custom log file name
	 * @return string
	 */
	public function getCustomLogFileName() {
		return (string)Mage::getStoreConfig(
			'turpentine_varnish/logging/custom_log_file_name' );
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
        } elseif( is_null( $arg ) ) {
            $value = 'NULL';
        }
        return $value;
    }
}
