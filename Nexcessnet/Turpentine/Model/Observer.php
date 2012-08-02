<?php

class Nexcessnet_Turpentine_Model_Observer {
    public function disableVarnishCaching( $observer ) {
        $cookieName = Mage::helper( 'turpentine/data' )->getNoCacheCookieName();
        if( !isset( $_COOKIE[$cookieName] ) ) {
            $this->_removeSetCookieHeader();
            Mage::getModel( 'core/cookie' )->set( $cookieName, '1' );
        }
        Mage::dispatchEvent( 'turpentine_varnish_disable' );
    }

    public function enableVarnishCaching( $observer ) {
        $cookieName = Mage::helper( 'turpentine/data' )->getNoCacheCookieName();
        if( isset( $_COOKIE[$cookieName] ) ) {
            Mage::getModel( 'core/cookie' )->delete(
                Mage::helper( 'turpentine' )->getNoCacheCookieName() );
        }
        Mage::dispatchEvent( 'turpentine_varnish_enable' );
    }

    public function bypassVarnishCaching( $observer ) {
        if( !headers_sent() ) {
            header( 'X-Varnish-Bypass: 1', true );
            Mage::dispatchEvent( 'turpentine_varnish_bypass' );
        }
    }

    protected function _removeSetCookieHeader() {
        if( !headers_sent() ) {
            // varnish will only "see" the first Set-Cookie header which would be
            // the frontend cookie, have to wipe it out so the no_cache cookie is
            // visible to varnish
            if( function_exists( 'header_remove' ) ) {
                //only exists in 5.3+
                header_remove( 'Set-Cookie' );
            } else {
                header( 'Set-Cookie:', true );
            }
            return true;
        } else {
            return false;
        }
    }
}
