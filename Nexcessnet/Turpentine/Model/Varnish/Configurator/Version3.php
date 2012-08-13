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
        $vcl = $this->_formatTemplate( file_get_contents( $tplFile ),
            $this->_getTemplateVars() );
        return $this->_cleanVcl( $vcl );
    }

    /**
     * Build the list of template variables to apply to the VCL template
     *
     * @return array
     */
    protected function _getTemplateVars() {
        $vars = array(
            'default_backend'   => $this->_getDefaultBackend(),
            'normalize_host_target' => $this->_getNormalizeHostTarget(),
            'url_base_regex'    => $this->getBaseUrlPathRegex(),
            'url_excludes'  => $this->_getUrlExcludes(),
            'get_param_excludes'    => $this->_getGetParamExcludes(),
            'default_ttl'   => $this->_getDefaultTtl(),
            'enable_get_excludes'   => ($this->_getGetParamExcludes() ? 'true' : 'false'),
            'cookie_excludes'  => $this->_getCookieExcludes(),
            'debug_headers' => $this->_getEnableDebugHeaders(),
            'grace_period'  => $this->_getGracePeriod(),
            'force_cache_static'    => $this->_getForceCacheStatic(),
            'static_extensions' => $this->_getStaticExtensions(),
            'static_ttl'    => $this->_getStaticTtl(),
            'url_ttls'      => $this->_getUrlTtls(),
        );
        if( Mage::getStoreConfig( 'turpentine_control/normalization/encoding' ) ) {
            $vars['normalize_encoding'] = $this->_vcl_sub_normalize_encoding();
        }
        if( Mage::getStoreConfig( 'turpentine_control/normalization/user_agent' ) ) {
            $vars['normalize_user_agent'] = $this->_vcl_sub_normalize_user_agent();
        }
        if( Mage::getStoreConfig( 'turpentine_control/normalization/host' ) ) {
            $vars['normalize_host'] = $this->_vcl_sub_normalize_host();
        }
        return $vars;
    }
}
