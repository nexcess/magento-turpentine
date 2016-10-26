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

class Nexcessnet_Turpentine_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Contains a newly generated v4 uuid whenever read, possibly not available
     * on all kernels
     */
    const UUID_SOURCE = '/proc/sys/kernel/random/uuid';

    /**
     * Compression level for serialization compression
     *
     * Testing showed no significant (size) difference between levels 1 and 9
     * so using 1 since it's faster
     */
    const COMPRESSION_LEVEL = 1;

    /**
     * Hash algorithm to use in various cryptographic methods
     */
    const HASH_ALGORITHM = 'sha256';

    /**
     * Cookie name for the Varnish bypass
     *
     * @var string
     */
    const BYPASS_COOKIE_NAME = 'varnish_bypass';

    /**
     * encryption singleton thing
     *
     * @var Mage_Core_Model_Encryption
     */
    protected $_crypt = null;

    /**
     * Like built-in explode() but applies trim to each exploded element and
     * filters out empty elements from result
     *
     * @param  string $token [description]
     * @param  string $data  [description]
     * @return array
     */
    public function cleanExplode($token, $data) {
        return array_filter(array_map('trim',
            explode($token, trim($data))));
    }

    /**
     * Generate a v4 UUID
     *
     * @return string
     */
    public function generateUuid() {
        if (is_readable(self::UUID_SOURCE)) {
            $uuid = trim(file_get_contents(self::UUID_SOURCE));
        } elseif (function_exists('mt_rand')) {
            /**
             * Taken from stackoverflow answer, possibly not the fastest or
             * strictly standards compliant
             * @link http://stackoverflow.com/a/2040279
             */
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),

                // 16 bits for "time_mid"
                mt_rand(0, 0xffff),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000,

                // 48 bits for "node"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        } else {
            // chosen by dice roll, guaranteed to be random
            $uuid = '4';
        }
        return $uuid;
    }

    /**
     * Get the Turpentine version
     *
     * @return string
     */
    public function getVersion() {
        return Mage::getConfig()
            ->getModuleConfig('Nexcessnet_Turpentine')->version;
    }

    /**
     * Base64 encode a string
     *
     * NOTE this changes the last 2 characters to be friendly to URLs
     *     / => .
     *     + => -
     *
     * @param  string $str
     * @return string
     */
    public function urlBase64Encode($str) {
        return str_replace(
            array('/', '+'),
            array('_', '-'),
            base64_encode($str) );
    }

    /**
     * Base64 decode a string, counterpart to urlBase64Encode
     *
     * @param  string $str
     * @return string
     */
    public function urlBase64Decode($str) {
        return base64_decode(
            str_replace(
                array('_', '-'),
                array('/', '+'),
                $str ) );
    }

    /**
     * Serialize a variable into a string that can be used in a URL
     *
     * Using gzdeflate to avoid the checksum/metadata overhead in gzencode and
     * gzcompress
     *
     * @param  mixed $data
     * @return string
     */
    public function freeze($data) {
        Varien_Profiler::start('turpentine::helper::data::freeze');
        $frozenData = $this->urlBase64Encode(
            $this->_getCrypt()->encrypt(
                gzdeflate(
                    serialize($data),
                    self::COMPRESSION_LEVEL ) ) );
        Varien_Profiler::stop('turpentine::helper::data::freeze');
        return $frozenData;
    }

    /**
     * Unserialize data
     *
     * @param  string $data
     * @return mixed
     */
    public function thaw($data) {
        Varien_Profiler::start('turpentine::helper::data::thaw');
        $thawedData = unserialize(
            gzinflate(
                $this->_getCrypt()->decrypt(
                    $this->urlBase64Decode($data) ) ) );
        Varien_Profiler::stop('turpentine::helper::data::thaw');
        return $thawedData;
    }

    /**
     * Get SHA256 hash of a string, salted with encryption key
     *
     * @param  string $data
     * @return string
     */
    public function secureHash($data) {
        $salt = $this->_getCryptKey();
        return hash(self::HASH_ALGORITHM, sprintf('%s:%s', $salt, $data));
    }

    /**
     * Get the HMAC hash for given data
     *
     * @param  string $data
     * @return string
     */
    public function getHmac($data) {
        return hash_hmac(self::HASH_ALGORITHM, $data, $this->_getCryptKey());
    }

    /**
     * Hash a cache key the same way blocks do
     *
     * @param  array $key
     * @return string
     */
    public function getCacheKeyHash($key) {
        return sha1(implode('|', array_values($key)));
    }

    /**
     * Get a list of child blocks inside the given block
     *
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return array
     */
    public function getChildBlockNames($blockNode) {
        return array_unique($this->_getChildBlockNames($blockNode));
    }

    /**
     * Get the getModel formatted name of a model classname or object
     *
     * @param  string|object $model
     * @return string
     */
    public function getModelName($model) {
        if (is_object($model)) {
            $model = get_class($model);
        }
        // This guess may work if the extension uses its lowercased name as model group name.
        $result = strtolower(preg_replace(
            '~^[^_]+_([^_]+)_Model_(.+)$~', '$1/$2', $model ));
        // This check is not expensive because the answer should come from Magento's classNameCache
        $checkModel = Mage::getConfig()->getModelClassName($result);
        if ('Mage_' == substr($checkModel, 0, 5) && ! class_exists($result)) {
            // Fallback to full model name.
            $result = $model;
        }
        return $result;
    }

    /**
     * Check config to see if Turpentine should handle the flash messages
     *
     * @return bool
     */
    public function useFlashMessagesFix() {
        return (bool) Mage::getStoreConfig(
            'turpentine_varnish/general/ajax_messages' );
    }

    /**
     * Check config to see if Turpentine should apply the product list toolbar
     * fix
     *
     * @return bool
     */
    public function useProductListToolbarFix() {
        return (bool) Mage::getStoreConfig(
            'turpentine_varnish/general/fix_product_toolbar' );
    }

    /**
     * Check if Turpentine should apply the new VCL on config changes
     *
     * @return bool
     */
    public function getAutoApplyOnSave() {
        return (bool) Mage::getStoreConfig(
            'turpentine_varnish/general/auto_apply_on_save' );
    }

    /**
     * Get config value specifying when to strip VCL whitespaces
     *
     * @return string
     */
    public function getVclFix() {
        return Mage::getStoreConfig(
            'turpentine_varnish/general/vcl_fix' );
    }

    /**
     * Get config value specifying when to strip VCL whitespaces
     *
     * @return string
     */
    public function getStripVclWhitespace() {
        return Mage::getStoreConfig(
            'turpentine_varnish/general/strip_vcl_whitespace' );
    }

    /**
     * Check if VCL whitespaces should be stripped for the given action
     *
     * @param string $action can be either "apply", "save" or "download"
     * @return bool
     */
    public function shouldStripVclWhitespace($action) {
        $configValue = $this->getStripVclWhitespace();
        if ($configValue === 'always') {
            return true;
        } elseif ($configValue === 'apply' && $action === 'apply') {
            return true;
        }
        return false;
    }

    /**
     * Get the cookie name for the Varnish bypass
     *
     * @return string
     */
    public function getBypassCookieName() {
        return self::BYPASS_COOKIE_NAME;
    }

    /**
     * The actual recursive implementation of getChildBlockNames
     *
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return array
     */
    protected function _getChildBlockNames($blockNode) {
        Varien_Profiler::start('turpentine::helper::data::_getChildBlockNames');
        if ($blockNode instanceof Mage_Core_Model_Layout_Element) {
            $blockNames = array((string) $blockNode['name']);
            foreach ($blockNode->xpath('./block | ./reference') as $childBlockNode) {
                $blockNames = array_merge($blockNames,
                    $this->_getChildBlockNames($childBlockNode));
                if ($this->getLayout() instanceof Varien_Simplexml_Config) {
                    foreach ($this->getLayout()->getNode()->xpath(sprintf(
                        '//reference[@name=\'%s\']', (string) $childBlockNode['name'] ))
                            as $childBlockLayoutNode) {
                        $blockNames = array_merge($blockNames,
                            $this->_getChildBlockNames($childBlockLayoutNode));

                    }
                }
            }
        } else {
            $blockNames = array();
        }
        Varien_Profiler::stop('turpentine::helper::data::_getChildBlockNames');
        return $blockNames;
    }

    /**
     * Get encryption singleton thing
     *
     * Not using core/cryption because it auto-base64 encodes stuff which we
     * don't want in this case
     *
     * @return Mage_Core_Model_Encryption
     */
    protected function _getCrypt() {
        if (is_null($this->_crypt)) {
            $this->_crypt = Varien_Crypt::factory()
                ->init($this->_getCryptKey());
        }
        return $this->_crypt;
    }

    /**
     * Get Magento's encryption key
     *
     * @return string
     */
    protected function _getCryptKey() {
        return (string) Mage::getConfig()->getNode('global/crypt/key');
    }
}
