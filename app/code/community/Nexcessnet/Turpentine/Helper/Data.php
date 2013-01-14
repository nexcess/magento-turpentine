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
    const UUID_SOURCE   = '/proc/sys/kernel/random/uuid';

    /**
     * encryption singleton thing
     *
     * @var Mage_Core_Model_Encryption
     */
    protected $_crypt   = null;

    /**
     * Like built-in explode() but applies trim to each exploded element and
     * filters out empty elements from result
     *
     * @param  string $token [description]
     * @param  string $data  [description]
     * @return array
     */
    public function cleanExplode( $token, $data ) {
        return array_filter( array_map( 'trim',
            explode( $token, trim( $data ) ) ) );
    }

    public function generateUuid() {
        if( is_readable( self::UUID_SOURCE ) ) {
            $uuid = trim( file_get_contents( self::UUID_SOURCE ) );
        } elseif( function_exists( 'mt_rand' ) ) {
            /**
             * Taken from stackoverflow answer, possibly not the fastest or
             * strictly standards compliant
             * @link http://stackoverflow.com/a/2040279
             */
            $uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

                // 16 bits for "time_mid"
                mt_rand( 0, 0xffff ),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand( 0, 0x0fff ) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand( 0, 0x3fff ) | 0x8000,

                // 48 bits for "node"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
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
        return Mage::getConfig()->getModuleConfig( 'Nexcessnet_Turpentine' )->version;
    }

    /**
     * Encrypt using Magento CE standard encryption (even on Magento EE)
     *
     * @param  string $data
     * @return string
     */
    public function encrypt( $data ) {
        return base64_encode( $this->_getCrypt()->encrypt( $data ) );
    }

    /**
     * Decrypt using Mage CE standard encryption (even on Magento EE)
     *
     * @param  string $data
     * @return string
     */
    public function decrypt( $data ) {
        return $this->_getCrypt()->decrypt( base64_decode( $data ) );
    }

    /**
     * Get a list of child blocks inside the given block
     *
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return array
     */
    public function getChildBlockNames( $blockNode ) {
        return array_unique( $this->_getChildBlockNames( $blockNode ) );
    }

    /**
     * Get the getModel formatted name of a model classname or object
     *
     * @param  string|object $model
     * @return string
     */
    public function getModelName( $model ) {
        if( is_object( $model ) ) {
            $model = get_class( $model );
        }
        return strtolower( preg_replace(
            '~^[^_]+_([^_]+)_Model_(.+)$~', '$1/$2', $model ) );
    }

    /**
     * Check config to see if Turpentine should handle the flash messages
     *
     * @return bool
     */
    public function useFlashMessagesFix() {
        return (bool)Mage::getStoreConfig(
            'turpentine_varnish/general/ajax_messages' );
    }

    /**
     * The actual recursive implementation of getChildBlockNames
     *
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return array
     */
    protected function _getChildBlockNames( $blockNode ) {
        if( $blockNode instanceof Mage_Core_Model_Layout_Element ) {
            $blockNames = array( (string)$blockNode['name'] );
            foreach( $blockNode->xpath( './block | ./reference' ) as $childBlockNode ) {
                $blockNames = array_merge( $blockNames,
                    $this->_getChildBlockNames( $childBlockNode ) );
            }
        } else {
            $blockNames = array();
        }
        return $blockNames;
    }

    /**
     * Get encryption singleton thing
     *
     * @return Mage_Core_Model_Encryption
     */
    protected function _getCrypt() {
        if( is_null( $this->_crypt ) ) {
            $this->_crypt = Mage::getModel( 'core/encryption' );
            $this->_crypt->setHelper( Mage::helper( 'core' ) );
        }
        return $this->_crypt;
    }
}
