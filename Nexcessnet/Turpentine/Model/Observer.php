<?php

class Nexcessnet_Turpentine_Model_Observer {
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
            $this->_removeSetCookieHeader();
            Mage::getModel( 'core/cookie' )->set( $cookieName, '1' );
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
            Mage::getModel( 'core/cookie' )->delete(
                Mage::helper( 'turpentine' )->getNoCacheCookieName() );
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
     * Remove any existing Set-Cookie headers
     *
     * Varnish will only "see" the first Set-Cookie header, which is usually the
     * frontend cookie. This method can be used to wipe it out so the no cache
     * cookie can be set and be visible to Varnish
     *
     * @return bool
     */
    protected function _removeSetCookieHeader() {
        if( !headers_sent() ) {
            if( function_exists( 'header_remove' ) ) {
                //only exists in 5.3+
                header_remove( 'Set-Cookie' );
            } else {
                //seems to not work in 5.3+, just sets a blank Set-Cookie header
                header( 'Set-Cookie:', true );
            }
            return true;
        } else {
            return false;
        }
    }
}
