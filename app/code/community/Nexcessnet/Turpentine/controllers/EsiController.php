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

class Nexcessnet_Turpentine_EsiController extends Mage_Core_Controller_Front_Action {
    /**
     * It seems this has to exist so we just make it redirect to the base URL
     * for lack of anything better to do.
     *
     * @return null
     */
    public function indexAction() {
        $this->getResponse()->setRedirect( Mage::getBaseUrl() );
    }

    /**
     * Spit out the rendered block from the URL-encoded data
     *
     * @return null
     */
    public function getBlockAction() {
        $resp = $this->getResponse();
        $cacheFlag = false;
        if( Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ) {
            $req = $this->getRequest();
            $esiHelper = Mage::helper( 'turpentine/esi' );
            $dataHelper = Mage::helper( 'turpentine/data' );
            $esiDataParamValue = $req->getParam( $esiHelper->getEsiDataParam() );
            $esiDataArray = $dataHelper->thaw( $esiDataParamValue );
            if( !$esiDataArray ) {
                Mage::log( 'Invalid ESI data in URL: ' . $esiDataParamValue,
                    Zend_Log::WARN );
                $resp->setHttpResponseCode( 500 );
                $resp->setBody( 'ESI data is not valid' );
            } elseif( !$esiHelper->getEsiDebugEnabled() &&
                    $esiDataArray['esi_method'] !==
                    $req->getParam( $esiHelper->getEsiMethodParam() ) ) {
                $resp->setHttpResponseCode( 403 );
                $resp->setBody( 'ESI method mismatch' );
                Mage::log( sprintf(
                        'Blocking change of ESI method: %s -> %s',
                        $esiDataArray['esi_method'],
                        $req->getParam( $esiHelper->getEsiMethodParam() ) ),
                    Zend_Log::WARN );
            } else {
                $esiData = new Varien_Object( $esiDataArray );
                $origRequest = Mage::app()->getRequest();
                Mage::app()->setCurrentStore(
                    Mage::app()->getStore( $esiData->getStoreId() ) );
                $appShim = Mage::getModel( 'turpentine/shim_mage_core_app' );
                $appShim->shim_setRequest( Mage::helper( 'turpentine/esi' )->
                    getDummyRequest() );
                $block = $this->_getEsiBlock( $esiData );
                if( $block ) {
                    $block->setEsiOptions( false );
                    $resp->setBody( $block->toHtml() );
                    if( (int)$req->getParam( $esiHelper->getEsiTtlParam() ) > 0 ) {
                        $cacheFlag = true;
                    }
                    if( $esiData->getEsiMethod() == 'ajax' ) {
                        $resp->setHeader( 'Access-Control-Allow-Origin',
                            $esiHelper->getCorsOrigin() );
                    }
                } else {
                    $resp->setHttpResponseCode( 404 );
                    $resp->setBody( 'ESI block not found' );
                }
                $appShim->shim_setRequest( $origRequest );
            }
        } else {
            $resp->setHttpResponseCode( 403 );
            $resp->setBody( 'ESI includes are not enabled' );
        }
        Mage::register( 'turpentine_nocache_flag', !$cacheFlag, true );
    }

    /**
     * Need to disable this flag to prevent setting the last URL but we
     * don't want to completely break sessions.
     *
     * see Mage_Core_Controller_Front_Action::postDispatch
     *
     * @return null
     */
    public function postDispatch() {
        $flag = $this->getFlag( '', self::FLAG_NO_START_SESSION );
        $this->setFlag( '', self::FLAG_NO_START_SESSION, true );
        parent::postDispatch();
        $this->setFlag( '', self::FLAG_NO_START_SESSION, $flag );
    }

    /**
     * Generate the ESI block
     *
     * @param  Varien_Object $esiData
     * @return Mage_Core_Block_Template
     */
    protected function _getEsiBlock( $esiData ) {
        foreach( $esiData->getSimpleRegistry() as $key => $value ) {
            Mage::register( $key, $value, true );
        }
        foreach( $esiData->getComplexRegistry() as $key => $data ) {
            $value = Mage::getModel( $data['model'] )->load( $data['id'] );
            Mage::register( $key, $value, true );
        }
        $layout = Mage::getSingleton( 'core/layout' );
        $design = Mage::getSingleton( 'core/design_package' )
            ->setPackageName( $esiData->getDesignPackage() )
            ->setTheme( $esiData->getDesignTheme() );
        $layoutUpdate = $layout->getUpdate();
        $layoutUpdate->load( $this->_swapCustomerHandles(
            $esiData->getLayoutHandles() ) );
        foreach( $esiData->getDummyBlocks() as $blockName ) {
            $layout->createBlock( 'Mage_Core_Block_Template', $blockName );
        }
        $layout->generateXml();
        $blockNode = current( $layout->getNode()->xpath( sprintf(
            '//block[@name=\'%s\']',
            $esiData->getNameInLayout() ) ) );
        $nodesToGenerate = Mage::helper( 'turpentine/data' )
            ->getChildBlockNames( $blockNode );
        Mage::getModel( 'turpentine/shim_mage_core_layout' )
            ->shim_generateFullBlock( $blockNode );
        foreach( $nodesToGenerate as $nodeName ) {
            foreach( $layout->getNode()->xpath( sprintf(
                    '//reference[@name=\'%s\']', $nodeName ) ) as $node ) {
                $layout->generateBlocks( $node );
            }
        }
        return $layout->getBlock( $esiData->getNameInLayout() );
    }

    /**
     * Swap customer_logged_in and customer_logged_out cached handles based on
     * actual customer login status. Fixes stale Login/Logout links (and
     * probably other things).
     *
     * This is definitely a hack, need a more general solution to this problem.
     *
     * @param  array $handles
     * @return array
     */
    protected function _swapCustomerHandles( $handles ) {
        if( Mage::helper( 'customer' )->isLoggedIn() ) {
            $replacement = array( 'customer_logged_out', 'customer_logged_in' );
        } else {
            $replacement = array( 'customer_logged_in', 'customer_logged_out' );
        }
        if( ( $pos = array_search( $replacement[0], $handles ) ) !== false ) {
            $handles[$pos] = $replacement[1];
        }
        return $handles;
    }
}
