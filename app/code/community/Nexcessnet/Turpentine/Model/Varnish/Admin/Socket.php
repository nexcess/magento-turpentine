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

/**
 * Based heavily on Tim Whitlock's VarnishAdminSocket.php from php-varnish
 * @link https://github.com/timwhitlock/php-varnish
 *
 * Copyright (c) 2010 Tim Whitlock
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * @method array help()
 * @method array ping()
 * @method array auth()
 * @method array banner()
 * @method array vcl_load()
 * @method array vcl_inline()
 * @method array vcl_use()
 * @method array vcl_discard()
 * @method array vcl_list()
 * @method array vcl_show()
 * @method array param_show()
 * @method array param_set()
 * Warning: ban_url does a non-lurker-friendly ban. This means it is not cleaned
 *          up from the ban list. A long ban list will slow down Varnish.
 * @method array ban_url()
 * @method array ban()
 * @method array ban_list()
 */
class Nexcessnet_Turpentine_Model_Varnish_Admin_Socket {

    // possible command return codes, from vcli.h
    const CODE_SYNTAX       = 100;
    const CODE_UNKNOWN      = 101;
    const CODE_UNIMPL       = 102;
    const CODE_TOOFEW       = 104;
    const CODE_TOOMANY      = 105;
    const CODE_PARAM        = 106;
    const CODE_AUTH         = 107;
    const CODE_OK           = 200;
    const CODE_CANT         = 300;
    const CODE_COMMS        = 400;
    const CODE_CLOSE        = 500;

    const READ_CHUNK_SIZE   = 1024;
    // varnish default, can only be changed at Varnish startup time
    // if data to write is over this limit the actual run-time limit is checked
    // and used
    const CLI_CMD_LENGTH_LIMIT  = 8192;

    /**
     * VCL config versions, should match config select values
     */
    static protected $_VERSIONS = array( '2.1', '3.0' );

    /**
     * Varnish socket connection
     *
     * @var resource
     */
    protected $_varnishConn = null;
    protected $_host = '127.0.0.1';
    protected $_port = 6082;
    protected $_private = null;
    protected $_authSecret = null;
    protected $_timeout = 5;
    protected $_version = null; //auto-detect

    public function __construct( array $options=array() ) {
        foreach( $options as $key => $value ) {
            switch( $key ) {
                case 'host':
                    $this->setHost( $value );
                    break;
                case 'port':
                    $this->setPort( $value );
                    break;
                case 'auth_secret':
                    $this->setAuthSecret( $value );
                    break;
                case 'timeout':
                    $this->setTimeout( $value );
                    break;
                case 'version':
                    $this->setVersion( $value );
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Provide simple Varnish methods
     *
     * Methods provided:
            help [command]
            ping [timestamp]
            auth response
            banner
            stats
            vcl.load <configname> <filename>
            vcl.inline <configname> <quoted_VCLstring>
            vcl.use <configname>
            vcl.discard <configname>
            vcl.list
            vcl.show <configname>
            param.show [-l] [<param>]
            param.set <param> <value>
            purge.url <regexp>
            purge <field> <operator> <arg> [&& <field> <oper> <arg>]...
            purge.list
     *
     * @param  string $name method name
     * @param  array $args method args
     * @return array
     */
    public function __call( $name, $args ) {
        array_unshift( $args, self::CODE_OK );
        array_unshift( $args, $this->_translateCommandMethod( $name ) );
        return call_user_func_array( array( $this, '_command' ), $args );
    }

    /**
     * Get the connection string for this socket (<host>:<port>)
     *
     * @return string
     */
    public function getConnectionString() {
        return sprintf( '%s:%d', $this->getHost(), $this->getPort() );
    }

    /**
     * Get the set host for this instance
     *
     * @return string
     */
    public function getHost() {
        return $this->_host;
    }

    /**
     * Set the Varnish host name/ip to connect to
     *
     * @param string $host hostname or ip
     */
    public function setHost( $host ) {
        $this->_close();
        $this->_host = $host;
        return $this;
    }

    /**
     * Get the port set for this instance
     *
     * @return int
     */
    public function getPort() {
        return $this->_port;
    }

    /**
     * Set the Varnish admin port
     *
     * @param int $port
     */
    public function setPort( $port ) {
        $this->_close();
        $this->_port = (int)$port;
        return $this;
    }

    /**
     * Set the Varnish admin auth secret, use null to indicate there isn't one
     *
     * @param string $authSecret
     */
    public function setAuthSecret( $authSecret=null ) {
        $this->_authSecret = $authSecret;
        return $this;
    }

    /**
     * Set the timeout to connect to the varnish instance
     *
     * @param int $timeout
     */
    public function setTimeout( $timeout ) {
        $this->_timeout = (int)$timeout;
        if( !is_null( $this->_varnishConn ) ) {
            stream_set_timeout( $this->_varnishConn, $this->_timeout );
        }
        return $this;
    }

    /**
     * Explicitly set the version of the varnish instance we're connecting to
     *
     * @param string $version version from $_VERSIONS
     */
    public function setVersion( $version ) {
        if( in_array( $version, self::$_VERSIONS ) ) {
            $this->_version = $version;
        } else {
            Mage::throwException( 'Unsupported Varnish version: ' . $version );
        }
    }

    /**
     * Check if we're connected to Varnish
     *
     * @return boolean
     */
    public function isConnected() {
        return !is_null( $this->_varnishConn );
    }

    /**
     * Find out what version mode we're running in
     *
     * @return string
     */
    public function getVersion() {
        if( is_null( $this->_version ) ) {
            $this->_version = $this->_determineVersion();
        }
        return $this->_version;
    }

    /**
     * Stop the Varnish instance
     */
    public function quit() {
        $this->_command( 'quit', self::CODE_CLOSE );
        $this->_close();
    }

    /**
     * Check if Varnish has a child running or not
     *
     * @return boolean
     */
    public function status() {
        $response = $this->_command( 'status' );
        if( !preg_match( '~Child in state (\w+)~', $response['text'], $match ) ) {
            return false;
        } else {
            return $match[1] === 'running';
        }
    }

    /**
     * Stop the running child (if it is running)
     *
     * @return $this
     */
    public function stop() {
        if( $this->status() ) {
            $this->_command( 'stop' );
        }
        return $this;
    }

    /**
     * Start running the Varnish child
     *
     * @return $this
     */
    public function start() {
        $this->_command( 'start' );
        return $this;
    }

    /**
     * Establish a connection to the configured Varnish instance
     *
     * @return array
     */
    protected function _connect() {
        $this->_varnishConn = fsockopen( $this->_host, $this->_port, $errno,
            $errstr, $this->_timeout );
        if( !is_resource( $this->_varnishConn ) ) {
            Mage::throwException( sprintf(
                'Failed to connect to Varnish on [%s:%d]: (%d) %s',
                $this->_host, $this->_port, $errno, $errstr ) );
        }

        stream_set_blocking( $this->_varnishConn, 1 );
        stream_set_timeout( $this->_varnishConn, $this->_timeout );

        //varnish 2.0 doesn't spit out a banner on connection, this will need
        //to be changed if 2.0 support is ever added
        $banner = $this->_read();
        if( $banner['code'] === self::CODE_AUTH ) {
            $challenge = substr( $banner['text'], 0, 32 );
            $response = hash( 'sha256', sprintf( "%s\n%s%s\n", $challenge,
                $this->_authSecret, $challenge ) );
            $this->_command( 'auth', self::CODE_OK, $response );
        } else if( $banner['code'] !== self::CODE_OK ) {
            Mage::throwException( 'Varnish admin authentication failed: ' .
                $banner['text'] );
        }

        return $this->isConnected();
    }

    /**
     * Close the connection (if we're connected)
     *
     * @return $this
     */
    protected function _close() {
        if( $this->isConnected() ) {
            fclose( $this->_varnishConn );
            $this->_varnishConn = null;
        }
        return $this;
    }

    /**
     * Write data to the Varnish instance, a newline is automatically appended
     *
     * @param  string $data data to write
     * @return $this
     */
    protected function _write( $data ) {
        if( is_null( $this->_varnishConn ) ) {
            $this->_connect();
        }
        $data = rtrim( $data ) . PHP_EOL;
        $dataLength = strlen( $data );
        if( $dataLength >= self::CLI_CMD_LENGTH_LIMIT ) {
            $cliBufferResponse = $this->param_show( 'cli_buffer' );
            if( preg_match( '~^cli_buffer\s+(\d+)\s+\[bytes\]~',
                    $cliBufferResponse['text'], $match ) ) {
                $realLimit = (int)$match[1];
            } else {
                Mage::helper( 'turpentine/debug' )->logWarn(
                    'Failed to determine Varnish cli_buffer limit, using default' );
                $realLimit = self::CLI_CMD_LENGTH_LIMIT;
            }
            if( $dataLength >= $realLimit ) {
                Mage::throwException( sprintf(
                    'Varnish data to write over length limit by %d characters',
                    $dataLength - $realLimit ) );
            }
        }
        if( ( $byteCount = fwrite( $this->_varnishConn, $data ) ) !== $dataLength ) {
            Mage::throwException( sprintf( 'Varnish socket write error: %d != %d',
                $byteCount, $dataLength ) );
        }
        return $this;
    }

    /**
     * Read a response from Varnish instance
     *
     * @return array tuple of the response (code, text)
     */
    protected function _read() {
        $code = null;
        $len = -1;
        while( !feof( $this->_varnishConn ) ) {
            $response = fgets( $this->_varnishConn, self::READ_CHUNK_SIZE );
            if( empty( $response ) ) {
                $streamMeta = stream_get_meta_data( $this->_varnishConn );
                if( $streamMeta['timed_out'] ) {
                    Mage::throwException( 'Varnish admin socket timeout' );
                }
            }
            if( preg_match( '~^(\d{3}) (\d+)~', $response, $match ) ) {
                $code = (int)$match[1];
                $len = (int)$match[2];
                break;
            }
        }

        if( is_null( $code ) ) {
            Mage::throwException( 'Failed to read response code from Varnish' );
        } else {
            $response = array( 'code' => $code, 'text' => '' );
            while( !feof( $this->_varnishConn ) &&
                    strlen( $response['text'] ) < $len ) {
                $response['text'] .= fgets( $this->_varnishConn,
                    self::READ_CHUNK_SIZE );
            }
            return $response;
        }
    }

    /**
     * [_command description]
     * @param  string  $verb       command name
     * @param  integer $okCode=200 code that indicates command was successful
     * @param  string  ...         command args
     * @return array
     */
    protected function _command( $verb, $okCode=200 ) {
        $params = func_get_args();
        //remove $verb
        array_shift( $params );
        //remove $okCode (if it exists)
        array_shift( $params );
        $cleanedParams = array();
        foreach( $params as $param ) {
            $cp = addcslashes( $param, "\"\\" );
            $cp = str_replace( PHP_EOL, '\n', $cp );
            $cleanedParams[] = sprintf( '"%s"', $cp );
        }
        $data = implode( ' ', array_merge(
            array( sprintf( '"%s"', $verb ) ),
            $cleanedParams ) );
        $response = $this->_write( $data )->_read();
        if( $response['code'] !== $okCode && !is_null( $okCode ) ) {
            Mage::helper( 'turpentine/debug' )->logDebug(
                'Error on Varnish command: %s', $data );
            Mage::throwException( sprintf(
                "Got unexpected response code from Varnish: %d\n%s",
                $response['code'], $response['text'] ) );
        } else {
            return $response;
        }
    }

    /**
     * Handle v2.1 <> v3.0 command compatibility
     *
     * @param  string $verb command to check
     * @return string
     */
    protected function _translateCommandMethod( $verb ) {
        $command = str_replace( '_', '.', $verb );
        switch( $this->getVersion() ) {
            case '2.1':
                $command = str_replace( 'ban', 'purge', $command );
                break;
            case '3.0':
                $command = str_replace( 'purge', 'ban', $command );
                break;
            default:
                Mage::throwException( 'Unrecognized Varnish version: ' .
                    $this->_version );
        }
        return $command;
    }

    /**
     * Guess the Varnish version based on the availability of the 'banner' command
     *
     * @return string
     */
    protected function _determineVersion() {
        $resp = $this->_write( 'help' )->_read();
        if( strpos( $resp['text'], 'ban.url' ) !== false ) {
            return '3.0';
        } elseif( strpos( $resp['text'], 'purge.url' ) !== false &&
                strpos( $resp['text'], 'banner' ) ) {
            return '2.1';
        } else {
            Mage::throwException( 'Unable to determine instance version' );
        }
    }
}
