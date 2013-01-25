<?php

/**
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

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

    protected function _getAdvancedSessionValidation() {
        $validation = '';
        foreach( $this->_getAdvancedSessionValidationTargets() as $target ) {
            $validation .= sprintf( 'hash_data(%s);' . PHP_EOL, $target );
        }
        return $validation;
    }

    /**
     * Build the list of template variables to apply to the VCL template
     *
     * @return array
     */
    protected function _getTemplateVars() {
        $vars = array(
            'default_backend'   => $this->_getDefaultBackend(),
            'admin_backend'     => $this->_getAdminBackend(),
            'admin_frontname'   => $this->_getAdminFrontname(),
            'normalize_host_target' => $this->_getNormalizeHostTarget(),
            'url_base_regex'    => $this->getBaseUrlPathRegex(),
            'url_excludes'  => $this->_getUrlExcludes(),
            'get_param_excludes'    => $this->_getGetParamExcludes(),
            'default_ttl'   => $this->_getDefaultTtl(),
            'enable_get_excludes'   => ($this->_getGetParamExcludes() ? 'true' : 'false'),
            'debug_headers' => $this->_getEnableDebugHeaders(),
            'grace_period'  => $this->_getGracePeriod(),
            'force_cache_static'    => $this->_getForceCacheStatic(),
            'static_extensions' => $this->_getStaticExtensions(),
            'static_ttl'    => $this->_getStaticTtl(),
            'url_ttls'      => $this->_getUrlTtls(),
            'enable_caching'    => $this->_getEnableCaching(),
            'crawler_acl'   => $this->_vcl_acl( 'crawler_acl',
                $this->_getCrawlerIps() ),
            'esi_cache_type_param'  =>
                Mage::helper( 'turpentine/esi' )->getEsiCacheTypeParam(),
            'esi_ttl_param' => Mage::helper( 'turpentine/esi' )->getEsiTtlParam(),
            'secret_handshake'  => Mage::helper( 'turpentine/varnish' )->getSecretHandshake(),
            'crawler_user_agent_regex'  => $this->_getCrawlerUserAgents(),
            // 'lru_factor'    => $this->_getLruFactor(),
            'advanced_session_validation'   => $this->_getAdvancedSessionValidation(),
        );
        if( Mage::getStoreConfig( 'turpentine_vcl/normalization/encoding' ) ) {
            $vars['normalize_encoding'] = $this->_vcl_sub_normalize_encoding();
        }
        if( Mage::getStoreConfig( 'turpentine_vcl/normalization/user_agent' ) ) {
            $vars['normalize_user_agent'] = $this->_vcl_sub_normalize_user_agent();
        }
        if( Mage::getStoreConfig( 'turpentine_vcl/normalization/host' ) ) {
            $vars['normalize_host'] = $this->_vcl_sub_normalize_host();
        }
        return $vars;
    }
}
