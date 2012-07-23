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

    protected function _formatTemplate( $template, array $vars ) {
        $needles = array_map( create_function( '$k', 'return "{{".$k."}}";' ),
            array_keys( $vars ) );
        $replacements = array_values( $vars );
        return str_replace( $needles, $replacements, $template );
    }

    protected function _vcl_call( $subroutine ) {
        return sprintf( 'call %s;', $subroutine );
    }

    protected function _getNormalizations() {
        return array( 'encoding', 'user_agent' );
    }

    protected function _getAdminFrontname() {
        if( Mage::getStoreConfig( 'admin/url/use_custom_path' ) ) {
            return Mage::getStoreConfig( 'admin/url/custom_path' );
        } else {
            return Mage::getConfig()->getNode(
                'admin/routers/adminhtml/args/frontName' );
        }
    }

    protected function _getNormalizeHostTarget() {
        $baseUrl = parse_url( Mage::getBaseUrl() );
        if( isset( $baseUrl['port'] ) ) {
            return sprintf( '%s:%d', $baseUrl['host'], $baseUrl['port'] );
        } else {
            return $baseUrl['host'];
        }
    }

    protected function _getUrlBase() {
        return parse_url( Mage::getBaseUrl(), PHP_URL_PATH );
    }

    protected function _getUrlExcludes() {
        return implode( '|', array_merge( array( $this->_getAdminFrontname(), 'api' ),
            array_map( 'trim', explode( PHP_EOL,
                Mage::getStoreConfig( 'turpentine_control/urls/url_blacklist' ) ) ) ) );
    }

    protected function _getUrlIncludes() {
        return implode( '|', array_map( 'trim', explode( PHP_EOL,
            Mage::getStoreConfig( 'turpentine_control/urls/url_whitelist' ) ) ) );
    }

    protected function _getGetExcludes() {
        return implode( '|', array_map( 'trim', explode( ',',
            Mage::getStoreConfig( 'turpentine_control/params/get_params' ) ) ) );
    }

    protected function _vcl_backend( $name, $host, $port ) {
        $tpl = <<<EOS
backend {{name}} {
    .host = "{{host}}";
    .port = "{{port}}";
}
EOS;
        $vars = array(
            'host'  => $host,
            'port'  => $port,
            'name'  => $name,
        );
        return $this->_formatTemplate( $tpl, $vars );
    }

    protected function _vcl_acl( $name, array $hosts ) {
        $tpl = <<<EOS
acl {{name}} {
    {{hosts}}
}
EOS;
        $fmtHost = create_function( '$h', 'return sprintf(\'"%s";\',$h);' );
        $vars = array(
            'name'  => $name,
            'hosts' => implode( PHP_EOL, array_map( $fmtHost, $hosts ) ),
        );
        return $this->_formatTemplate( $tpl, $vars );
    }

    protected function _getAllKeys() {
        $keyNames = array( 'default_backend', 'purge_acl', 'normalize_host_target',
            'normalize_encoding', 'normalize_user_agent', 'url_includes',
            'normalize_host', 'url_base', 'url_excludes', 'get_excludes' );
        $keys = array();
        foreach( $keyNames as $key ) {
            $keys[$key] = '';
        }
        return $keys;
    }
}
