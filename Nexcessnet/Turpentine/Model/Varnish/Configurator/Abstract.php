<?php

class Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {
    public function __construct( $options=array() ) {

    }

    public function generate() {

    }

    public function save( $generatedConfig ) {
        $filename = Mage::getConfig('turpentine_servers/servers/config_file');
        mkdir( dirname( $filename ), true );
        file_put_contents( $generatedConfig, $filename );
    }
}
