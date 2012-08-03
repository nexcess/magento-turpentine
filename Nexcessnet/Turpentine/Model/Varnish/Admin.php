<?php

class Nexcessnet_Turpentine_Model_Varnish_Admin {

    /**
     * Flush all Magento URLs in Varnish cache
     *
     * @return bool
     */
    public function flushAll() {
        $url = Mage::getStoreConfig( 'web/unsecure/base_url' ) . '.*';
        return $this->_doPurgeRequest( $url )->isSuccessful();
    }

    /**
     * Flush all Magento URLs matching the given (relative) regex
     *
     * @param  string $pattern regex to match against URLs
     * @return bool
     */
    public function flushUrl( $pattern ) {
        $url = Mage::getStoreConfig( 'web/unsecure/base_url' ) . $pattern;
        return $this->_doPurgeRequest( $url )->isSuccessful();
    }

    /**
     * Send a purge request to Varnish
     *
     * @param  string $url             URL pattern to purge
     * @param  string $method='DELETE' HTTP method to use for purge request
     * @return Zend_Http_Response
     */
    protected function _doPurgeRequest( $url, $method='DELETE' ) {
        // using zend client here instead of varien because the varien client
        // wouldn't do DELETE requests
        $client = new Zend_Http_Client();
        if( Zend_Uri::check( $url ) ) {
            $client->setUri( $url );
            $resp = $client->request( $method );
        } else {
            //allow more regex chars in the URL
            Zend_Uri::setConfig( array( 'allow_unwise' => true ) );
            $client->setUri( $url );
            $resp = $client->request( $method );
            Zend_Uri::setConfig( array( 'allow_unwise' => false ) );
        }
        return $resp;
    }
}
