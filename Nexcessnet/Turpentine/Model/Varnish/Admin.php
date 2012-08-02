<?php

class Nexcessnet_Turpentine_Model_Varnish_Admin {
    public function flushAll( $configurator ) {
        $url = Mage::getStoreConfig( 'web/unsecure/base_url' ) . '.*';
        return $this->_doPurgeRequest( $url )->isSuccessful();
    }

    public function flushUrl( $configurator, $pattern ) {
        $url = Mage::getStoreConfig( 'web/unsecure/base_url' ) . $pattern;
        return $this->_doPurgeRequest( $url )->isSuccessful();
    }

    protected function _doPurgeRequest( $url, $method='DELETE' ) {
        // using zend client here instead of varien because the varien client
        // wouldn't do DELETE requests
        $client = new Zend_Http_Client();
        if( Zend_Uri::check( $url ) ) {
            $client->setUri( $url );
            $resp = $client->request( $method );
        } else {
            Zend_Uri::setConfig( array( 'allow_unwise' => true ) );
            $client->setUri( $url );
            $resp = $client->request( $method );
            Zend_Uri::setConfig( array( 'allow_unwise' => false ) );
        }
        return $resp;
    }
}
