<?php

class Nexcessnet_Turpentine_Model_Varnish_Configurator_Version3
    extends Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {

    const VCL_TEMPLATE_FILE = 'version-3.vcl';

    public function generate() {
        $tplFile = $this->_getVclTemplateFilename( self::VCL_TEMPLATE_FILE );
        $tpl = file_get_contents( $tplFile );
        return $this->_formatTemplate( $tpl, $this->_getTemplateVars() );
    }

    protected function _getTemplateVars() {
        $vars = array(
            'admin_name'    => $this->_getAdminFrontname(),
            'default_backend'   =>
                $this->_vcl_backend( 'default', 'localhost', '80' ),
            'purge_acl'     =>
                $this->_vcl_acl( 'purge_trusted', array( 'localhost' ) ),
            'normalize_host_target' => $this->_getNormalizeHostTarget(),
            'url_base'      => $this->_getUrlBase(),
        );
        foreach( $this->_getNormalizations() as $subr ) {
            $name = 'normalize_' . $subr;
            $vars[$name] = $this->_vcl_call( $name );
        }
        return array_merge( $this->_getAllKeys(), $vars );
    }
}
