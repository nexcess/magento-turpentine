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

class Nexcessnet_Turpentine_Model_Observer_Esi {
    public function prepareHttpResponse( $eventObject ) {
        if( Mage::getSingleton( 'turpentine/'))
    }

    public function injectEsi( $eventObject ) {
        $blockObject = $eventObject->getBlock();

        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $esiOptions = $blockObject->getEsi() ) {

            $blockObject->setTemplate( 'turpentine/esi.phtml' );
            $esiData = $this->_getEsiData( $blockObject, $esiOptions );
            //get this now so we don't include stuff added later
            $esiDataHash = $this->_getEsiDataHash( $esiData );

            if( array_key_exists( 'registry_keys', $esiOptions ) ) {
                $keys = array_map( 'trim',
                    explode( ',', $esiOptions['registry_keys'] ) );
                $registry = array_combine(
                    $keys,
                    array_map( array( 'Mage', 'registry' ), $keys ) );
                $esiData->setRegistry( $registry );
            }
            $esiData->setUrl( Mage::getUrl( 'turpentine/esi/getBlock', array(
                'cacheType'     => $esiData->getCacheType(),
                'ttl'           => $esiData->getTtl(),
                Mage::helper( 'turpentine/esi' )->getEsiDataIdParam()
                                => $esiDataHash, //hash
            ) ) );

            $tags = array(
                'TURPENTINE_ESI_BLOCK',
                'TURPENTINE_ESI_CACHETYPE_' . $esiData->getCacheType(),
                'TURPENTINE_ESI_BLOCKTYPE_' . $esiData->getBlockType(),
                'TURPENTINE_ESI_BLOCKNAME_' . $esiData->getNameInLayout(),
            );
            Mage::app()->getCache()->save( serialize( $esiData ),
                $esiDataHash, $tags, null );
            $blockObject->setEsiData( $esiData );

            //flag request for ESI processing
            Mage::getSingleton( 'turpentine/sentinel' )->setEsiFlag( true );
        }
    }

    protected function _getEsiData( $blockObject, $esiOptions ) {
        $esiOptions = array_merge(
            array(
                'cache_type'    => 'global',
            ),
            $esiOptions );

        $esiData = new Varien_Object();
        $esiData->setStoreId( Mage::app()->getStore()->getId() );
        $esiData->setDesignPackage( Mage::getDesign()->getPackageName() );
        $esiData->setDesignTheme( Mage::getDesign()->getTheme( 'layout' ) );
        $esiData->setNameInLayout( $blockObject->getNameInLayout() );
        switch( $esiOptions['cache_type'] ) {
            case 'global':
                $ttlKey = 'turpentine_esi/ttl/per_global';
                break;
            case 'per-page':
                $ttlKey = 'turpentine_esi/ttl/per_page';
                $esiData->setParentUrl( Mage::app()->getRequest()
                    ->getRequestString() );
                break;
            case 'per-client':
                $ttlKey = 'turpentine_esi/ttl/per_client';
                break;
            case default:
                Mage::throwException( 'Invalid block cache_type: ' .
                    $esiOptions['cache_type'] );
        }
        $esiData->setCacheType( $esiOptions['cache_type'] );
        if( array_key_exists( 'ttl', $esiOptions ) ) {
            $esiData->setTtl( $esiOptions['ttl'] );
        } else {
            $esiData->setTtl( Mage::getStoreConfig( $ttlKey ) );
        }
        $esiData->setBlockType( get_class( $blockObject ) );

        return $esiData;
    }

    protected function _getEsiDataHash( $esiData ) {
        $hashData = $esiData->toArray();
        sort( $hashData );
        return sha1( $this->_getHashSalt() . serialize( $hashData ) );
    }

    protected function _getHashSalt() {
        return Mage::getStoreConfig( 'turpentine_servers/servers/auth_key' ) . ':';
    }
}
