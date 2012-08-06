<?php

/**
 * Based heavily on Tim Whitlock's VarnishAdminSocket.php from php-varnish
 */
abstract class Nexcessnet_Turpentine_Model_Varnish_Admin_Socket {

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

    protected $_varnishConn = null;
    protected $_host = '127.0.0.1';
    protected $_port = 6082;
    protected $_private = null;
    protected $_authSecret = null;
    protected $_timeout = 5;

    public function __call( $name, $args ) {
        array_unshift( $args, self::CODE_OK );
        array_unshift( $args, str_replace( '_', '.', $name ) );
        call_user_func_array( array( $this, '_command' ), $args );
    }

    public function setHost( $host ) {
        $this->_host = $host;
    }

    public function setPort( $port ) {
        $this->_port = $port;
    }

    public function setAuthSecret( $authSecret ) {
        $this->_authSecret = $authSecret;
    }

    public function setTimeout( $timeout ) {
        $this->_timeout = $timeout;
        if( !is_null( $this->_varnishConn ) ) {
            stream_set_timeout( $this->_varnishConn, $this->_timeout );
        }
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

    protected function _connect() {
        $this->_varnishConn = fsockopen( $this->_host, $this->_port, $errno,
            $errstr, $this->_timeout );
        if( !is_resource( $this->_varnishConn ) ) {
            //raise magento exception
            throw new Exception();
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
            //throw exception
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
        $data .= PHP_EOL;
        $byteCount = fputs( $this->_varnishConn, $data );
        if( $byteCount !== strlen( $data ) ) {
            //write error, throw exception
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
                    //timeout, raise exception
                }
            }
            if( preg_match( '~^(\d{3}) (\d+)~', $response, $match ) ) {
                $code = (int)$match[1];
                $len = (int)$match[2];
                break;
            }
        }

        if( is_null( $code ) ) {
            //raise exception
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
        foreach( $params as $param ) {
            $cleanedParams[] = '"' . addcslashes( $param, "\"\n" ) . '"';
        }
        $data = implode( ' ', array_merge( array( $verb ), $cleanedParams ) );
        $response = $this->_write( $data )->_read();
        if( $response['code'] !== $okCode && !is_null( $okCode ) ) {
            //throw exception
        } else {
            return $response;
        }
    }
}
