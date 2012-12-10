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

/**
 * The Magento autoloader is apparently not smart enough to figure this out or
 * something, so we'll hack around it. It seems the "controllers" dir is
 * deprecated or something, need to look into updating this or something.
 *
 * We can't check if it already exists because apparently that also triggers
 * the autoloader. Lovely.
 */
// if( !class_exists( 'Nexcessnet_Turpentine_EsiController' ) ) {
    require_once Mage::getModuleDir( '', 'Nexcessnet_Turpentine' ) .
        '/controllers/EsiController.php';
// }
class Nexcessnet_Turpentine_AjaxController extends Nexcessnet_Turpentine_EsiController {

    /**
     * Render the block out from the URL encoded data
     *
     * @return null
     */
    public function getBlockAction() {
        $resp = $this->getResponse();
        if( Mage::helper( 'turpentine/ajax' )->shouldResponseUseAjax() ) {
            $req = $this->getRequest();
            $ajaxDataParamValue = $req->getParam(
                Mage::helper( 'turpentine/ajax' )->getAjaxDataParam() );
            $ajaxDataArray = unserialize( Mage::helper( 'turpentine/data' )
                ->decrypt( $ajaxDataParamValue ) );
            if( !$ajaxDataArray ) {
                Mage::log( 'Invalid AJAX data in URL: ' . $ajaxDataParamValue,
                    Zend_Log::WARN );
                $resp->setHttpResponseCode( 500 );
                $resp->setBody( 'AJAX data is not valid' );
            } else {
                $esiData = new Varien_Object( $ajaxDataArray );
                $origRequest = Mage::app()->getRequest();
                Mage::app()->setCurrentStore(
                    Mage::app()->getStore( $esiData->getStoreId() ) );
                Mage::app()->setRequest( $this->_getDummyRequest() );
                $block = $this->_getEsiBlock( $esiData );
                if( $block ) {
                    $block->setAjaxOptions( false );
                    $resp->setBody( $block->toHtml() );
                } else {
                    $resp->setHttpResponseCode( 404 );
                    $resp->setBody( 'AJAX block not found' );
                }
                Mage::app()->setRequest( $origRequest );
            }
        } else {
            $resp->setHttpResponseCode( 403 );
            $resp->setBody( 'AJAX includes are not enabled' );
        }
        // ajax is never cached
        Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
    }
}
