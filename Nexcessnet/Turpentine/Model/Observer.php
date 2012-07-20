<?php

class Nexcessnet_Turpentine_Model_Observer {
    public function disableVarnishCaching( $observer ) {
        $cookie = Mage::getModel( 'core/cookie' );
        if( $cookie->get( 'varnish_nocache' ) !== '1' ) {
            $cookie->set( 'varnish_nocache', '1' );
        }
        Mage::dispatchEvent( 'turpentine_varnish_disable' );
    }

    public function enableVarnishCaching( $observer ) {
        Mage::getModel( 'core/cookie' )->delete( 'varnish_nocache' );
        Mage::dispatchEvent( 'turpentine_varnish_enable' );
    }

    public function bypassVarnishCaching( $observer ) {
        if( !headers_sent() ) {
            header( 'X-Varnish-Bypass: 1' );
            Mage::dispatchEvent( 'turpentine_varnish_bypass' );
        }
    }
}
