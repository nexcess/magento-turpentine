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

class Nexcessnet_Turpentine_Model_Mage_Shim_Layout extends Mage_Core_Model_Layout {
    /**
     * Generate a full block instead of just it's decendents
     *
     * @param  Mage_Core_Model_Layout $layout
     * @param  Mage_Core_Model_Layout_Element $blockNode
     * @return null
     */
    static public function generateFullBlock( $blockNode ) {
        $layout = Mage::getSingleton( 'core/layout' );
        if( !( $parent = $blockNode->getParent() ) ) {
            $parent = new Varien_Object();
        }
        $layout->_generateBlock( $blockNode, $parent );
        return $layout->generateBlocks( $blockNode );
    }
}
