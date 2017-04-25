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

class Nexcessnet_Turpentine_Model_Varnish_Admin {

    const MASK_ESI_SYNTAX       = 0x2;
    const URL_ESI_SYNTAX_FIX    = 'https://github.com/nexcess/magento-turpentine/wiki/FAQ#wiki-i-upgraded-to-turpentine-06-and-are-the-add-to-cart-buttons-look-broken';

    /**
     * Flush all Magento URLs in Varnish cache
     *
     * @return bool
     */
    public function flushAll() {
        return $this->flushUrl('.*');
    }

    /**
     * Flush all Magento URLs matching the given (relative) regex
     *
     * @param  string $subPattern regex to match against URLs
     * @return bool
     */
    public function flushUrl($subPattern) {
        $result = array();
        foreach (Mage::helper('turpentine/varnish')->getSockets() as $socket) {
            $socketName = $socket->getConnectionString();
            try {
                // We don't use "ban_url" here, because we want to do lurker friendly bans.
                // Lurker friendly bans get cleaned up, so they don't slow down Varnish.
                $socket->ban('obj.http.X-Varnish-URL', '~', $subPattern);
            } catch (Mage_Core_Exception $e) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Flush according to Varnish expression
     *
     * @param  mixed ...
     * @return array
     */
    public function flushExpression() {
        $args = func_get_args();
        $result = array();
        foreach (Mage::helper('turpentine/varnish')->getSockets() as $socket) {
            $socketName = $socket->getConnectionString();
            try {
                call_user_func_array(array($socket, 'ban'), $args);
            } catch (Mage_Core_Exception $e) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Flush all cached objects with the given content type
     *
     * @param  string $contentType
     * @return array
     */
    public function flushContentType($contentType) {
        return $this->flushExpression(
            'obj.http.Content-Type', '~', $contentType );
    }

    /**
     * Generate and apply the config to the Varnish instances
     *
     * @return bool
     */
    public function applyConfig() {
        $result = array();
        $helper = Mage::helper('turpentine');
        foreach (Mage::helper('turpentine/varnish')->getSockets() as $socket) {
            $cfgr = Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract::getFromSocket($socket);
            $socketName = $socket->getConnectionString();
            if (is_null($cfgr)) {
                $result[$socketName] = 'Failed to load configurator';
            } else {
                $vcl = $cfgr->generate($helper->shouldStripVclWhitespace('apply'));
                $vclName = 'vcl_' . Mage::helper('turpentine/data')
                    ->secureHash(microtime());
                try {
                    $this->_testEsiSyntaxParam($socket);
                    $socket->vcl_inline($vclName, $vcl);
                    sleep(1); //this is probably not really needed
                    $socket->vcl_use($vclName);
                } catch (Mage_Core_Exception $e) {
                    $result[$socketName] = $e->getMessage();
                    continue;
                }
                $result[$socketName] = true;
            }
        }
        return $result;
    }

    /**
     * Get a configurator based on the first socket in the server list
     *
     * @return Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract
     */
    public function getConfigurator() {
        $sockets = Mage::helper('turpentine/varnish')->getSockets();
        $cfgr = Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract::getFromSocket($sockets[0]);
        return $cfgr;
    }

    protected function _testEsiSyntaxParam($socket) {
        $session = Mage::getSingleton('adminhtml/session');
        $helper = Mage::helper('turpentine/varnish');
        $result = false;

        if ($helper->csrfFixupNeeded()) {
            if ($socket->getVersion() === '4.0' || $socket->getVersion() === '4.1') {
                $paramName = 'feature';
                $value = $socket->param_show($paramName);
                $value = explode("\n", $value['text']);
                if (isset($value[1]) && strpos($value[1], '+esi_ignore_other_elements') !== false) {
                    $result = true;
                } else {
                    $session->addWarning('Varnish <em>feature</em> param is '.
                        'not set correctly, please see <a target="_blank" href="'.
                        self::URL_ESI_SYNTAX_FIX.'">these instructions</a> '.
                        'to fix this warning.');
                }
            } else {
                $paramName = 'esi_syntax';
                $value = $socket->param_show($paramName);
                if (preg_match('~(\d)\s+\[bitmap\]~', $value['text'], $match)) {
                    $value = hexdec($match[1]);
                    if ($value & self::MASK_ESI_SYNTAX) { //bitwise intentional
                        // setting is correct, all is fine
                        $result = true;
                    } else {
                        $session->addWarning('Varnish <em>esi_syntax</em> param is '.
                            'not set correctly, please see <a target="_blank" href="'.
                            self::URL_ESI_SYNTAX_FIX.'">these instructions</a> '.
                            'to fix this warning.');
                    }
                }
            }

            if ($result === false) {
                // error
                Mage::helper('turpentine/debug')->logWarn(
                    sprintf('Failed to parse param.show output to check %s value', $paramName) );
                $result = true;
            }
        } else {
            $result = true;
        }

        return $result;
    }
}
