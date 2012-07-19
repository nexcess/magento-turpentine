<?php

class Nexcessnet_Turpentine_Model_Varnish_Configurator_Version3
    extends Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {

    const VCL_TEMPLATE_FILE = 'version-3.vcl';

    public function generate() {
        return $this->_formatTemplate( self::VCL_TEMPLATE_FILE,
            $this->_getTemplateVars() );
    }

    protected function _getTemplateVars() {
        $vars = array(
            'admin_name'    => 'admin',
        );
        foreach( $this->_getNormalizations() as $subr ) {
            $name = 'normalize_' . $subr;
            $vars[$name] = $this->_vcl_call( $name );
        }
        return $vars;
    }
}
