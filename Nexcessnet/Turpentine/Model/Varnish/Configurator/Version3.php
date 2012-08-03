<?php

class Nexcessnet_Turpentine_Model_Varnish_Configurator_Version3
    extends Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {

    const VCL_TEMPLATE_FILE = 'version-3.vcl';

    /**
     * Generate the Varnish 3.0-compatible VCL
     *
     * @return string
     */
    public function generate() {
        $tplFile = $this->_getVclTemplateFilename( self::VCL_TEMPLATE_FILE );
        $tpl = file_get_contents( $tplFile );
        return $this->_formatTemplate( $tpl, $this->_getTemplateVars() );
    }

    /**
     * Build the list of template variables to apply to the VCL template
     *
     * @return array
     */
    protected function _getTemplateVars() {
        $vars = array(
            'default_backend'   =>
                $this->_vcl_backend( 'default',
                    Mage::getStoreConfig( 'turpentine_servers/backend/backend_host' ),
                    Mage::getStoreConfig( 'turpentine_servers/backend/backend_port' ) ),
            'purge_acl'     =>
                $this->_vcl_acl( 'purge_trusted', array( 'localhost', '127.0.0.1' ) ),
            'normalize_host_target' => $this->_getNormalizeHostTarget(),
            'url_base'      => $this->_getUrlBase(),
            'url_excludes'  => $this->_getUrlExcludes(),
            'get_excludes'  => $this->_getGetExcludes(),
            'default_ttl'   => $this->_getDefaultTtl(),
            'no_cache_cookies'  => implode( '|', array_merge( array(
                Mage::helper( 'turpentine' )->getNoCacheCookieName(),
                'adminhtml' ) ) ),
            'debug_headers' => 'true',
        );
        foreach( $this->_getNormalizations() as $subr ) {
            $name = 'normalize_' . $subr;
            $vars[$name] = $this->_vcl_call( $name );
        }
        return $vars;
    }
}
