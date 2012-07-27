<?php

class Nexcessnet_Turpentine_Model_Varnish_Admin {
    public function flushAll( $configurator ) {
        $client = new Varien_Http_Client( $configurator->getUrlBase() . '.*' );
        $client->setMethod( Varien_Http_Client::DELETE );
        $resp = $client->request();
        return $resp->isSuccessful();
    }

    public function flushUrl( $configurator, $pattern ) {

    }
}
