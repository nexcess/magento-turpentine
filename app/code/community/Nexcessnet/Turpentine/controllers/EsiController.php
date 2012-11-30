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
        Mage::helper( 'turpentine/esi' )->ensureEsiEnabled();
        $req = $this->getRequest();
        $esiDataParamValue = $req->getParam(
            Mage::helper( 'turpentine/esi' )->getEsiDataParam() );
        $esiDataArray = unserialize( Mage::helper( 'turpentine/data' )
            ->decrypt( $esiDataParamValue ) );
        $resp = $this->getResponse();
        if( !$esiDataArray ) {
            Mage::log( 'Invalid ESI data in URL: ' . $esiDataParamValue, Zend_Log::WARN );
            $resp->setHttpResponseCode( 500 );
            $resp->setBody( 'Invalid ESI data in URL' );
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
    }

    protected function _getEsiBlock( $esiData ) {
        return $this->_testEsiGeneration( $esiData );
        return $this->_generateEsiBlock( $this->_doEsiLayoutSetup( $esiData ),
            $esiData->getNameInLayout() );
    }

    protected function _testEsiGeneration( $esiData ) {
        foreach( $esiData->getRegistry() as $key => $value ) {
            Mage::register( $key, $value, true );
        }
        $layout = Mage::getSingleton( 'core/layout' );
        $design = Mage::getSingleton( 'core/design_package' )
            ->setPackageName( $esiData->getDesignPackage() )
            ->setTheme( $esiData->getDesignTheme() );
        $layoutUpdate = $layout->getUpdate();
        $layoutUpdate->load( $esiData->getLayoutHandles() );
        $layoutXml = $layoutUpdate->getFileLayoutUpdatesXml(
            $design->getArea(),
            $design->getPackageName(),
            $design->getTheme( 'layout' ),
            $esiData->getStoreId() );
        foreach( $esiData->getDummyBlocks() as $blockName ) {
            $layout->createBlock( 'Mage_Core_Block_Template', $blockName );
        }
        $layout->generateXml();
        $layoutShim = Mage::getModel( 'turpentine/mage_shim_layout' );
        $blockNode = current( $layout->getNode()->xpath( sprintf(
            '//block[@name=\'%s\']',
            $esiData->getNameInLayout() ) ) );
        $nodesToGenerate = array_unique( $this->_getChildBlocks( $blockNode ) );
        $layoutShim::generateFullBlock( $blockNode );
        foreach( $nodesToGenerate as $nodeName ) {
            foreach( $layout->getNode()->xpath( sprintf(
                    '//reference[@name=\'%s\']', $nodeName ) ) as $node ) {
                $layout->generateBlocks( $node );
            }
        }
        return $layout->getBlock( $esiData->getNameInLayout() );
    }

    protected function _getChildBlocks( $node ) {
        $blocks = array( (string)$node['name'] );
        $childNodes = $node->xpath( './block | ./reference' );
        foreach( $childNodes as $childNode ) {
            $blocks = array_merge( $blocks, $this->_getChildBlocks( $childNode ) );
        }
        return $blocks;
    }

    /**
     * Generate the ESI block output
     *
     * @param  array $handles
     * @param  string $blockNameInLayout name of the block to generate
     * @return bool
     */
    protected function _generateEsiBlock( $handles, $blockNameInLayout ) {
        $layout = Mage::getSingleton( 'core/layout' );
        foreach( $handles as $handle ) {
            $handleName = $handle->getName();
            $layout->getUpdate()->addHandle( $handleName );
            $layout->getUpdate()->load();
            $layout->generateXml();
            $layout->generateBlocks();

            if( $block = $layout->getBlock( $blockNameInLayout ) ) {
                return $block;
            } else {
                //reset for next loop
                Mage::app()->removeCache( $layout->getUpdate()->getCacheId() );
                $layout->getUpdate()->removeHandle( $handleName );
                $layout->getUpdate()->resetUpdates();
            }
        }
        //never found the block, indicate as such
        return null;
    }

    /**
     * Setup the layout for ESI block generation
     *
     * @param  Varien_Object $esiData
     * @return array
     */
    protected function _doEsiLayoutSetup( $esiData ) {
        //restore the cached registry
        foreach( $esiData->getRegistry() as $key => $value ) {
            Mage::register( $key, $value, true );
        }
        $layout = Mage::getSingleton( 'core/layout' );
        $design = Mage::getSingleton( 'core/design_package' )
            ->setPackageName( $esiData->getDesignPackage() )
            ->setTheme( $esiData->getDesignTheme() );
        $layoutUpdate = $layout->getUpdate();
        $layoutXml = $layoutUpdate->getFileLayoutUpdatesXml(
            $design->getArea(),
            $design->getPackageName(),
            $design->getTheme( 'layout' ),
            $esiData->getStoreId() );
        //create any dummy blocks needed
        foreach( $esiData->getDummyBlocks() as $blockName ) {
            $layout->createBlock( 'Mage_Core_Block_Template', $blockName );
        }
        $handles = $layoutXml->xpath( sprintf(
            '//block[@name=\'%s\']/ancestor::node()[last()-2]',
            $esiData->getNameInLayout() ) );
        return $handles;
    }
}
