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

class Nexcessnet_Turpentine_Model_Varnish_Configurator_Version4
    extends Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {

    const VCL_TEMPLATE_FILE = 'version-4.vcl';

    /**
     * Generate the Varnish 4.0-compatible VCL
     *
     * @param bool $doClean if true, VCL will be cleaned (whitespaces stripped, etc.)
     * @return string
     */
    public function generate($doClean = true) {
        // first, check if a custom template is set
        $customTemplate = $this->_getCustomTemplateFilename();
        if ($customTemplate) { 
            $tplFile = $customTemplate;
        } else { 
            $tplFile = $this->_getVclTemplateFilename(self::VCL_TEMPLATE_FILE);
        }
        $vcl = $this->_formatTemplate(file_get_contents($tplFile),
            $this->_getTemplateVars());
        return $doClean ? $this->_cleanVcl($vcl) : $vcl;
    }

    // TODO: Check this
    protected function _getAdvancedSessionValidation() {
        $validation = '';
        foreach ($this->_getAdvancedSessionValidationTargets() as $target) {
            $validation .= sprintf('hash_data(%s);'.PHP_EOL, $target);
        }
        return $validation;
    }

    /**
     * Build the list of template variables to apply to the VCL template
     *
     * @return array
     */
    protected function _getTemplateVars() {
        $vars = parent::_getTemplateVars();
        $vars['advanced_session_validation'] =
            $this->_getAdvancedSessionValidation();

        if (Mage::getStoreConfig('turpentine_vcl/backend/load_balancing') != 'no') {
            $vars['directors']          = $this->_vcl_directors();
            $vars['admin_backend_hint'] = 'vdir_admin.backend()';
            $vars['set_backend_hint']   = 'set req.backend_hint = vdir.backend();';
        } else {
            $vars['directors']          = '';
            $vars['admin_backend_hint'] = 'admin';
            $vars['set_backend_hint']   = '';
        }

        return $vars;
    }

    protected function _vcl_directors()
    {
        $tpl = <<<EOS
    new vdir       = directors.round_robin();
    new vdir_admin = directors.round_robin();

EOS;

        if ('yes_admin' == Mage::getStoreConfig('turpentine_vcl/backend/load_balancing')) {
            $adminBackendNodes = Mage::helper('turpentine/data')->cleanExplode(PHP_EOL,
                Mage::getStoreConfig('turpentine_vcl/backend/backend_nodes_admin'));
        } else {
            $adminBackendNodes = Mage::helper('turpentine/data')->cleanExplode(PHP_EOL,
                Mage::getStoreConfig('turpentine_vcl/backend/backend_nodes'));
        }

        $backendNodes = Mage::helper('turpentine/data')->cleanExplode(PHP_EOL,
            Mage::getStoreConfig('turpentine_vcl/backend/backend_nodes'));

        for ($i = 0, $iMax = count($backendNodes); $i < $iMax; $i++) {
            $tpl .= <<<EOS
    vdir.add_backend(web{$i});

EOS;
        }

        for ($i = 0, $iMax = count($adminBackendNodes); $i < $iMax; $i++) {
            $tpl .= <<<EOS
    vdir_admin.add_backend(webadmin{$i});

EOS;
        }

        $vars = array();

        return $this->_formatTemplate($tpl, $vars);
    }

    /**
     * Format a VCL director declaration, for load balancing
     *
     * @param string $name           name of the director, also used to select config settings
     * @param array  $backendOptions options for each backend
     * @return string
     */
    protected function _vcl_director($name, $backendOptions) {
        $tpl = <<<EOS
{{backends}}
EOS;
        if ('admin' == $name && 'yes_admin' == Mage::getStoreConfig('turpentine_vcl/backend/load_balancing')) {
            $backendNodes = Mage::helper('turpentine/data')->cleanExplode(PHP_EOL,
                Mage::getStoreConfig('turpentine_vcl/backend/backend_nodes_admin'));
            $probeUrl = Mage::getStoreConfig('turpentine_vcl/backend/backend_probe_url_admin');
            $prefix = 'admin';
        } else {
            $backendNodes = Mage::helper('turpentine/data')->cleanExplode(PHP_EOL,
                Mage::getStoreConfig('turpentine_vcl/backend/backend_nodes'));
            $probeUrl = Mage::getStoreConfig('turpentine_vcl/backend/backend_probe_url');

            if ('admin' == $name) {
                $prefix = 'admin';
            } else {
                $prefix = '';
            }
        }

        $backends = '';
        $number = 0;
        foreach ($backendNodes as $backendNode) {
            $parts = explode(':', $backendNode, 2);
            $host = (empty($parts[0])) ? '127.0.0.1' : $parts[0];
            $port = (empty($parts[1])) ? '80' : $parts[1];
            $backends .= $this->_vcl_director_backend($host, $port, $prefix.$number, $probeUrl, $backendOptions);

            $number++;
        }
        $vars = array(
            'name' => $name,
            'backends' => $backends
        );
        return $this->_formatTemplate($tpl, $vars);
    }

    /**
     * Format a VCL backend declaration to put inside director
     *
     * @param string $host       backend host
     * @param string $port       backend port
     * @param string $descriptor backend descriptor
     * @param string $probeUrl   URL to check if backend is up
     * @param array  $options    extra options for backend
     * @return string
     */
    protected function _vcl_director_backend($host, $port, $descriptor, $probeUrl = '', $options = array()) {
        $tpl = <<<EOS
        backend web{$descriptor} {
            .host = "{{host}}";
            .port = "{{port}}";
{{probe}}

EOS;
        $vars = array(
            'host'  => $host,
            'port'  => $port,
            'probe' => ''
        );
        if ( ! empty($probeUrl)) {
            $vars['probe'] = $this->_vcl_get_probe($probeUrl);
        }
        $str = $this->_formatTemplate($tpl, $vars);
        foreach ($options as $key => $value) {
            $str .= sprintf('            .%s = %s;', $key, $value).PHP_EOL;
        }
        $str .= <<<EOS
        }

EOS;
        return $str;
    }
}
