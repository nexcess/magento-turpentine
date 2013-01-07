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

class Nexcessnet_Turpentine_Model_Shim_Mage_Core_Layout extends Mage_Core_Model_Layout {
    /**
     * Generate a full block instead of just it's decendents
     *
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return null
     */
    public function shim_generateFullBlock( $blockNode ) {
        $layout = $this->_shim_getLayout();
        if( !( $parent = $blockNode->getParent() ) ) {
            $parent = new Varien_Object();
        }
        $layout->_generateBlock( $blockNode, $parent );
        return $layout->generateBlocks( $blockNode );
    }

    /**
     * Apply the layout action node for a block
     *
     * @param  Mage_Core_Model_Layout_Element $node
     * @return Mage_Core_Model_Layout
     */
    public function shim_generateAction( $node ) {
        if( !( $parentNode = $node->getParent() ) ) {
            $parentNode = new Varien_Object();
        }
        return $this->_shim_getLayout()->_generateAction( $node, $parentNode );
    }

    /**
     * Get the layout singleton
     *
     * @return Mage_Core_Model_Layout
     */
    protected function _shim_getLayout() {
        return Mage::getSingleton( 'core/layout' );
    }
}
