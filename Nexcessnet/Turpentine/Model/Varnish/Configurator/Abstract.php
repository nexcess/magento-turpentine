<?php

abstract class Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {
    public function __construct( $options=array() ) {

    }

    abstract public function generate();
    abstract protected function _getTemplateVars();

    public function save( $generatedConfig ) {
        $filename = Mage::getStoreConfig( 'turpentine_servers/servers/config_file' );
        mkdir( dirname( $filename ), true );
        file_put_contents( $generatedConfig, $filename );
    }

    protected function _getVclTemplateFilename( $baseFilename ) {
        $extensionDir = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
        return sprintf( '%s/misc/%s', $extensionDir, $baseFilename );
    }

    protected function _formatTemplate( $templateName, array $vars ) {
        $tpl = file_get_contents( $this->_getVclTemplateFilename( $templateName ) );
        $needles = array_map( create_function( '$k', 'return "{{".$k."}}";' ),
            array_keys( $vars ) );
        $replacements = array_values( $vars );
        return str_replace( $needles, $replacements, $templateName );
    }

    protected function _vcl_call( $subroutine ) {
        return sprintf( 'call %s;', $subroutine );
    }

    protected function _getNormalizations() {
        return array( 'encoding', 'user_agent', 'host' );
    }
}
