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

class Nexcessnet_Turpentine_Model_Observer_Ajax extends
        Nexcessnet_Turpentine_Model_Observer_Esi {

    /**
     * Encode block data in URL then replace with AJAX template
     *
     * @link https://github.com/nexcess/magento-turpentine/wiki/ESI_Cache_Policy
     *
     * Events: core_block_abstract_to_html_before
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function injectAjax( $eventObject ) {
        $blockObject = $eventObject->getBlock();
        $ajaxHelper = Mage::helper( 'turpentine/ajax' );
        if( $ajaxHelper->shouldResponseUseAjax() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $ajaxOptions = $blockObject->getAjaxOptions() ) {
            if( Mage::app()->getStore()->getCode() == 'admin' ) {
                //admin blocks are not allowed to be cached for now
                Mage::log( 'Ignoring attempt to AJAX inject adminhtml block: ' .
                    $blockObject->getNameInLayout(), Zend_Log::WARN );
                return;
            }
            $dataParam = $ajaxHelper->getAjaxDataParam();
            $ajaxOptions = array_merge( $this->_getDefaultAjaxOptions(),
                $ajaxOptions );
            //change the block's template to the stripped down ESI template
            $blockObject->setTemplate( 'turpentine/ajax.phtml' );
            //esi data is the data needed to regenerate the ESI'd block
            $ajaxData = $this->_getEsiData( $blockObject, $ajaxOptions )->toArray();
            ksort( $ajaxData );
            $ajaxUrl = Mage::getUrl( 'turpentine/ajax/getBlock',
                array(
                    //we probably don't really need to encrypt this but it doesn't hurt
                    //use core/encryption instead of Mage::encrypt/decrypt because
                    //EE uses a different method by default
                    $dataParam      => Mage::helper( 'turpentine/data' )
                                        ->encrypt( serialize( $ajaxData ) ),
            ) );
            $blockObject->setAjaxUrl( $ajaxUrl );
            // avoid caching the ESI template output to prevent the double-esi-
            // include/"ESI processing not enabled" bug
            foreach( array( 'lifetime', 'tags', 'key' ) as $dataKey ) {
                $blockObject->unsetData( 'cache_' . $dataKey );
            }
            if( strlen( $ajaxUrl ) > 2047 ) {
                Mage::log( 'AJAX url is probably to long (> 2047 characters): ' .
                    $ajaxUrl, Zend_Log::WARN );
            }
        } // else handle the block like normal and cache it inline with the page
    }

    /**
     * Get the default AJAX options
     *
     * @return array
     */
    protected function _getDefaultAjaxOptions() {
        return array(
            'dummy_blocks'      => '',
            'registry_keys'     => '',
        );
    }
}
