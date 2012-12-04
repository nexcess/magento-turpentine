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
                $block = $this->_getEsiBlock( $esiData );
                if( $block ) {
                    $block->setEsiOptions( false );
                    $resp->setBody( $block->toHtml() );
                } else {
                    $resp->setHttpResponseCode( 404 );
                    $resp->setBody( 'ESI block not found' );
                    Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
                }
            }
        } else {
            $resp->setHttpResponseCode( 403 );
            $resp->setBody( 'ESI includes are not enabled' );
            Mage::getSingleton( 'turpentine/sentinel' )->setCacheFlag( false );
        }
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
        $layoutUpdate->load( $esiData->getLayoutHandles() );
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
            if( $nodeName != (string)$blockNode['name'] ) {
                foreach( $layout->getNode()->xpath( sprintf(
                        '//reference[@name=\'%s\']', $nodeName ) ) as $node ) {
                    $layout->generateBlocks( $node );
                }
            }
        }
        return $layout->getBlock( $esiData->getNameInLayout() );
    }
}
