<?php

/**
 * Based heavily on Tim Whitlock's VarnishAdminSocket.php from php-varnish
 */
class Nexcessnet_Turpentine_Model_Varnish_Admin_Socket {

    //from vcli.h
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
    //varnish default, can be changed at Varnish startup time but it's probably not
    //worth the trouble to dynamically detect at runtime
    const CLI_CMD_LENGTH_LIMIT  = 8192;

    protected $_varnishConn = null;
    protected $_host = '127.0.0.1';
    protected $_port = 6082;
    protected $_private = null;
    protected $_authSecret = null;
    protected $_timeout = 5;
    protected $_version = '3.0';

    static protected $_VERSIONS = array( '2.1', '3.0' );

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
                    $this->setVersion( $version );
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

    public function setHost( $host ) {
        $this->_host = $host;
    }

    public function setPort( $port ) {
        $this->_port = (int)$port;
    }

    public function setAuthSecret( $authSecret ) {
        $this->_authSecret = $authSecret;
    }

    public function setTimeout( $timeout ) {
        $this->_timeout = (int)$timeout;
        if( !is_null( $this->_varnishConn ) ) {
            stream_set_timeout( $this->_varnishConn, $this->_timeout );
        }
    }

    public function setVersion( $version ) {
        if( in_array( $version, self::$_VERSIONS ) ) {
            $this->_version = $version;
        } else {
            Mage::throwException( 'Unsupported Varnish version: ' . $version );
        }
    }

    public function isConnected() {
        return !is_null( $this->_varnishConn );
    }

    public function getVersion() {
        if( is_null( $this->_version ) ) {
            $this->_version = $this->_determineVersion();
        }
        return $this->_version;
    }

    public function quit() {
        $this->_command( 'quit', self::CODE_CLOSE );
        $this->close();
    }

    public function status() {
        $response = $this->_command( 'status' );
        if( !preg_match( '~Child in state (\w+)~', $response['text'], $match ) ) {
            return false;
        } else {
            return $match[1] === 'running';
        }
    }

    public function stop() {
        if( $this->status() ) {
            $this->_command( 'stop' );
        }
        return $this;
    }

    public function start() {
        $this->_command( 'start' );
        return $this;
    }
/*
    public function stats() {
        return $this->_command( 'stats' );
    }

    public function vcl_load( $configname, $filename ) {
        return $this->_command( 'vcl.load', self::CODE_OK, $configname, $filename );
    }

    public function vcl_inline( $configname, $vclstring ) {
        return $this->_command( 'vcl.inline', self::CODE_OK, $configname, $vclstring );
    }

    public function vcl_use( $configname ) {
        return $this->_command( 'vcl.use', self::CODE_OK, $configname );
    }

    public function vcl_discard( $configname ) {
        return $this->_command( 'vcl.discard', self::CODE_OK, $configname );
    }

    public function vcl_list() {
        return $this->_command( 'vcl.list' );
    }

    public function vcl_show( $configname ) {
        return $this->_command( 'vcl.show', self::CODE_OK, $configname );
    }

    public function purge_url( $regexp ) {
        return $this->_command(x, self::CODE_OK, $regexp );
    }

    public function purge() {
        return $this->_command(x, self::CODE_OK );
    }

    public function purge_list() {
        return $this->_command( 'purge.list' );
    }

    public function param_show( $param ) {
        return $this->_command( 'param.show', self::CODE_OK, $param );
    }

    public function param_set( $param, $value ) {
        return $this->_command( 'param.set', self::CODE_OK, $param, $value );
    }
*/
    protected function _connect() {
        $this->_varnishConn = fsockopen( $this->_host, $this->_port, $errno,
            $errstr, $this->_timeout );
        if( !is_resource( $this->_varnishConn ) ) {
            Mage::throwException( sprintf(
                'Failed to connect to Varnish on %s:%d',
                $this->_host, $this->_port ) );
        }

        stream_set_blocking( $this->_varnishConn, 1 );
        stream_set_timeout( $this->_varnishConn, $this->_timeout );

        $banner = $this->_read();

        if( $banner['code'] === self::CODE_AUTH ) {
            $challenge = substr( $banner['text'], 0, 32 );
            $response = hash( 'sha256', sprintf( "%s\n%s%s\n", $challenge,
                $this->_authSecret, $challenge ) );
            $banner = $this->_command( 'auth', self::CODE_OK, $response );
        } else if( $banner['code'] !== self::CODE_OK ) {
            Mage::throwException( 'Varnish admin authentication failed: ' .
                $banner['text'] );
        }

        return $banner;
    }

    protected function _close() {
        if( !is_null( $this->_varnishConn ) ) {
            fclose( $this->_varnishConn );
            $this->_varnishConn = null;
        }
    }

    protected function _write( $data ) {
        if( is_null( $this->_varnishConn ) ) {
            $this->_connect();
        }
        $data = rtrim( $data ) . PHP_EOL;
        if( strlen( $data ) >= self::CLI_CMD_LENGTH_LIMIT ) {
            Mage::throwException( 'Varnish data to write over length limit' );
        }
        $byteCount = fputs( $this->_varnishConn, $data );
        if( $byteCount !== strlen( $data ) ) {
            Mage::throwException( sprintf( 'Varnish socket write error: %d != %d',
                $byteCount, strlen( $data ) ) );
        }
        return $this;
    }

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

    protected function _command( $verb, $okCode=200 ) {
        $params = func_get_args();
        //remove $verb
        array_shift( $params );
        //remove $okCode (if it exists)
        array_shift( $params );
        $cleanedParams = array();
        $useHereDoc = false;
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
            Mage::throwException( sprintf(
                "Got unexpected response code from Varnish: %d\n%s",
                $response['code'], $response['text'] ) );
        } else {
            return $response;
        }
    }

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

    protected function _determineVersion() {
        $this->_write( 'banner' );
        $result = $this->_read();
        if( $result['code'] === self::CODE_OK ) {
            return '3.0';
        } elseif( $result['code'] === self::CODE_UNKNOWN ) {
            return '2.1';
        } else {
            Mage::throwException( 'Unable to determine Varnish version' );
        }
    }
}
