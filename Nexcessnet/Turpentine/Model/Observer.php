<?php

class Nexcessnet_Turpentine_Model_Observer extends Varien_Event_Observer {
    /**
     * disable varnish caching for the client by setting the no cache cookie (if
     * it's not already set)
     *
     * @param  mixed $observer
     * @return null
     */
    public function disableVarnishCaching( $observer ) {
        $cookieName = Mage::helper( 'turpentine/data' )->getNoCacheCookieName();
        if( !isset( $_COOKIE[$cookieName] ) ) {
            $frontend = $this->_getCookieHeaderValue( 'frontend' );
            $this->_removeHeader( 'Set-Cookie' );
            Mage::getModel( 'core/cookie' )->set( $cookieName, '1' );
            if( !is_null( $frontend ) ) {
                Mage::getModel( 'core/cookie' )->set( 'frontend', $frontend );
            }
            Mage::dispatchEvent( 'turpentine_varnish_disable' );
        }
    }

    /**
     * Enable varnish caching by removing the no cache cookie (if it exists)
     *
     * @param  mixed $observer
     * @return null
     */
    public function enableVarnishCaching( $observer ) {
        $cookieName = Mage::helper( 'turpentine/data' )->getNoCacheCookieName();
        if( isset( $_COOKIE[$cookieName] ) ) {
            Mage::getModel( 'core/cookie' )->delete( $cookieName );
            Mage::dispatchEvent( 'turpentine_varnish_enable' );
        }
    }

    /**
     * Bypass varnish caching for this response, obviously this will only work
     * if varnish hasn't already cached this page.
     *
     * @param  mixed $observer
     * @return null
     */
    public function bypassVarnishCaching( $observer ) {
        if( !headers_sent() ) {
            header( 'X-Varnish-Bypass: 1', true );
            Mage::dispatchEvent( 'turpentine_varnish_bypass' );
        }
    }

    /**
     * Flush the URL associated with the event
     *
     * The event data should include a data_object that has a getUrlPath method
     *
     * @param  mixed $observer
     * @return null
     */
    public function flushVarnishUrl( $observer ) {
        $data = $observer->getData();
        if( isset( $data['event'] ) ) {
            $data = $data['event']->getData();
            if( isset( $data['data_object'] ) ) {
                if( Mage::getModel( 'turpentine/varnish_admin' )
                        ->flushUrl( '.*' . $data['data_object']->getUrlPath() ) ) {
                    Mage::getSingleton( 'core/session' )
                        ->addSuccess( Mage::helper( 'turpentine' )
                            ->__( 'Flushed object URL in Varnish.' ) );
                } else {
                    Mage::getSingleton( 'core/session' )
                        ->addNotice( Mage::helper( 'turpentine' )
                            ->__( 'Failed to flush object URL in Varnish.' ) );
                }
            }
        }
    }

    /**
     * Register auto-purge events
     *
     * @param  mixed $observer
     * @return null
     */
    public function registerEvents( $observer ) {
        $events = array_filter( array_map( 'trim', explode( PHP_EOL,
            Mage::getStoreConfig(
                'turpentine_control/purging/auto_purge_actions' ) ) ) );
        foreach( $events as $event ) {
            Mage::getModel( 'turpentine/mage_shim' )->addEventObserver(
                'admin', $event, 'turpentine', 'model', get_class( $this ),
                'flushVarnishUrl' );
        }
    }

    protected function _getCurrentHeaders() {
        if( function_exists( 'headers_list' ) ) {
            $headers = array();
            foreach( headers_list() as $header ) {
                list( $key, $value ) = explode( ':', $header );
                $value = trim( $value );
                if( isset( $headers[$key] ) && is_array( $headers[$key] ) ) {
                    $headers[$key][] = $value;
                } elseif( isset( $headers[$key] ) ) {
                    $headers[$key] = array( $headers[$key], $value );
                } else {
                    $headers[$key] = $value;
                }
            }
            return $headers;
        } else {
            return array();
        }
    }

    protected function _getCookieHeaderValue( $cookieName ) {
        $headers = $this->_getCurrentHeaders();
        if( isset( $headers['Set-Cookie'] ) ) {
            $parts = array_map( 'trim', explode( ';', $headers['Set-Cookie'] ) );
            var_dump( $parts );
            foreach( $parts as $part ) {
                if( strpos( $part, $cookieName ) === 0 ) {
                    list( $cn, $value ) = explode( '=', $part );
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * Remove the specified header
     *
     * Varnish will only "see" the first instance of a given header, which is
     * problematic for the Set-Cookie header since it's usually the
     * frontend cookie. This method can be used to wipe it out so the no cache
     * cookie can be set and be visible to Varnish
     *
     * @return bool
     */
    protected function _removeHeader( $header ) {
        if( !headers_sent() ) {
            if( function_exists( 'header_remove' ) ) {
                //only exists in 5.3+
                header_remove( $header );
            } else {
                //seems to not work in 5.3+, just sets a blank Set-Cookie header
                header( sprintf( '%s:', $header ), true );
            }
            return true;
        } else {
            return false;
        }
    }
}
