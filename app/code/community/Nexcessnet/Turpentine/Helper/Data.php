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
     * The actual recursive implementation of getChildBlockNames
     *
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return array
     */
    protected function _getChildBlockNames( $blockNode ) {
        $blockNames = array( (string)$blockNode['name'] );
        foreach( $blockNode->xpath( './block | ./reference' ) as $childBlockNode ) {
            $blockNames = array_merge( $blockNames,
                $this->_getChildBlockNames( $childBlockNode ) );
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
