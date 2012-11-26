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
    public function indexAction() {
        return $this->getBlockAction();
    }

    /**
     * Spit out the rendered block from the cached data
     *
     * @return [type]
     */
    public function getBlockAction() {
        Mage::helper( 'turpentine/esi' )->ensureEsiEnabled();
        $req = $this->getRequest();
        $esiData = new Varien_Object( unserialize(
            Mage::helper( 'core' )->decrypt(
                base64_decode( $req->getParam(
                    Mage::helper( 'turpentine/esi' )->getEsiDataIdParam() ) ) ) ) );
        $esiOptions = array(
            'cache_type'    => $req->getParam( 'cacheType' ),
            'ttl'           => $req->getParam( 'ttl' ),
        );
        //restore the cached registry
        foreach( $esiData->getRegistry() as $key => $value ) {
            Mage::register( $key, $value, true );
        }
        $layout = Mage::getSingleton( 'core/layout' );
        $design = Mage::getSingleton( 'core/design_package' )
            ->setPackageName( $esiData->getDesignPackage() )
            ->setTheme( $esiData->getDesignTheme() );
        $layoutXml = $layout->getUpdate()->getFileLayoutUpdatesXml(
            $design->getArea(),
            $design->getPackageName(),
            $design->getTheme( 'layout' ),
            $esiData->getStoreId() );
        $handles = $layoutXml->xpath( sprintf(
            '//block[@name=\'%s\']/ancestor::node()[last()-2]',
            $esiData->getNameInLayout() ) );
        //create any dummy blocks needed
        foreach( $esiData->getDummyBlocks() as $blockName ) {
            $layout->createBlock( 'Mage_Core_Block_Template', $blockName );
        }
        foreach( $handles as $handle ) {
            $handleName = $handle->getName();
            $layout->getUpdate()->addHandle( $handleName );
            $layout->getUpdate()->load();
            $layout->generateXml();
            $layout->generateBlocks();

            if( $block = $layout->getBlock( $esiData->getNameInLayout() ) ) {
                //disable ESI flag on the block to avoid infinite loop
                $block->setEsi( false );
                $this->getResponse()->setBody( $block->toHtml() );
                return;
            }
            //reset for next loop
            Mage::app()->removeCache( $layout->getUpdate()->getCacheId() );
            $layout->getUpdate()->removeHandle( $handleName );
            $layout->getUpdate()->resetUpdates();
        }
    }
}
