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
    public function getEsiBlockAction() {
        $resp = $this->getResponse();
        if( Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ) {
            $req = $this->getRequest();
            $esiDataParamValue = $req->getParam(
                Mage::helper( 'turpentine/esi' )->getEsiDataParam() );
            $esiDataArray = unserialize( Mage::helper( 'turpentine/data' )
                ->decrypt( $esiDataParamValue ) );
            if( !$esiDataArray ) {
                Mage::log( 'Invalid ESI data in URL: ' . $esiDataParamValue, Zend_Log::WARN );
                $resp->setHttpResponseCode( 500 );
                $resp->setBody( 'ESI data is not valid' );
                //this wouldn't be cached anyway but we'll set this just in case
                Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
            } else {
                $esiData = new Varien_Object( $esiDataArray );
                $origRequest = Mage::app()->getRequest();
                Mage::app()->setCurrentStore(
                    Mage::app()->getStore( $esiData->getStoreId() ) );
                Mage::app()->setRequest( $this->_getDummyRequest() );
                $block = $this->_getEsiBlock( $esiData );
                if( $block ) {
                    $block->setEsiOptions( false );
                    $resp->setBody( $block->toHtml() );
                } else {
                    $resp->setHttpResponseCode( 404 );
                    $resp->setBody( 'ESI block not found' );
                    Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
                }
                Mage::app()->setRequest( $origRequest );
            }
        } else {
            $resp->setHttpResponseCode( 403 );
            $resp->setBody( 'ESI includes are not enabled' );
            Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
        }
    }

    public function getAjaxBlockAction() {
        $resp = $this->getResponse();
        if( Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ) {
            $req = $this->getRequest();
            $esiDataParamValue = $req->getParam(
                Mage::helper( 'turpentine/esi' )->getEsiDataParam() );
            $esiDataArray = unserialize( Mage::helper( 'turpentine/data' )
                ->decrypt( $esiDataParamValue ) );
            if( !$esiDataArray ) {
                Mage::log( 'Invalid ESI data in URL: ' . $esiDataParamValue, Zend_Log::WARN );
                $resp->setHttpResponseCode( 500 );
                $resp->setBody( 'ESI data is not valid' );
                //this wouldn't be cached anyway but we'll set this just in case
                Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
            } else {
                $esiData = new Varien_Object( $esiDataArray );
                $origRequest = Mage::app()->getRequest();
                Mage::app()->setCurrentStore(
                    Mage::app()->getStore( $esiData->getStoreId() ) );
                Mage::app()->setRequest( $this->_getDummyRequest() );
                $block = $this->_getEsiBlock( $esiData );
                if( $block ) {
                    $block->setAjaxOptions( false );
                    $resp->setBody( $block->toHtml() );
                    Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
                } else {
                    $resp->setHttpResponseCode( 404 );
                    $resp->setBody( 'ESI block not found' );
                    Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
                }
                Mage::app()->setRequest( $origRequest );
            }
        } else {
            $resp->setHttpResponseCode( 403 );
            $resp->setBody( 'ESI includes are not enabled' );
            Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
        }
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
        $flag = $this->getFlag( 'getBlock', self::FLAG_NO_START_SESSION );
        $this->setFlag( 'getBlock', self::FLAG_NO_START_SESSION, true );
        parent::postDispatch();
        $this->setFlag( 'getBlock', self::FLAG_NO_START_SESSION, $flag );
    }

    /**
     * Get dummy request to base URL
     *
     * Used to pretend that the request was for the base URL instead of
     * turpentine/esi/getBlock while rendering ESI blocks. Not perfect, but may
     * be good enough
     *
     * @return Mage_Core_Controller_Request_Http
     */
    protected function _getDummyRequest() {
        return new Mage_Core_Controller_Request_Http( Mage::getBaseUrl() );
    }

    /**
     * Generate the ESI block
     *
     * @param  Varien_Object $esiData
     * @return Mage_Core_Block_Template
     */
    protected function _getEsiBlock( $esiData ) {
        foreach( $esiData->getRegistry() as $key => $value ) {
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
            ->generateFullBlock( $blockNode );
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
