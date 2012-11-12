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

class Nexcessnet_Turpentine_Model_Observer_Esi extends Varien_Event_Observer {

    /**
     * Check the ESI flag and set the ESI header if needed
     *
     * Events: http_response_send_before
     *
     * @param [type] $eventObject [description]
     */
    public function setFlagHeaders( $eventObject ) {
        $response = $eventObject->getResponse();
        $sentinel = Mage::getSingleton( 'turpentine/sentinel' );
        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() ) {
            Mage::log( 'Setting ESI flag to: ' . $sentinel->getEsiFlag() );
            $response->setHeader( 'X-Turpentine-Esi',
                $sentinel->getEsiFlag() ? '1' : '0' );
        }
    }

    /**
     * Allows disabling page-caching by setting the cache flag on a block
     *
     *     <turpentine_cache_flag value="0" />
     *
     *
     * Events: controller_action_layout_generate_blocks_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function checkCacheFlag( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $layout = $eventObject->getLayout();
            $layoutXml = $layout->getUpdate()->asSimplexml();
            foreach( $layoutXml->xpath( '//turpentine_cache_flag' ) as $node ) {
                foreach( $node->attributes() as $attr => $value ) {
                    if( $attr == 'value' ) {
                        if( !$value ) {
                            Mage::getSingleton( 'turpentine/sentinel' )
                                ->setCacheFlag( false );
                            return; //only need to set the flag once
                        }
                    }
                }
            }
        }
    }

    /**
     * Cache block content then replace with ESI template
     *
     * Events: core_block_abstract_to_html_before
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function injectEsi( $eventObject ) {
        $blockObject = $eventObject->getBlock();

        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $esiOptions = $blockObject->getEsi() ) {

            //change the block's template to the stripped down ESI tag
            $blockObject->setTemplate( 'turpentine/esi.phtml' );
            $esiData = $this->_getEsiData( $blockObject, $esiOptions );
            //get this now so we don't include stuff added later
            $esiDataHash = $this->_getEsiDataHash( $esiData );
            $esiData->setDebugId( $this->_getEsiDebugId( $esiDataHash ) );
            if( isset( $esiOptions['registry_keys'] ) ) {
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
                                => $esiDataHash,
            ) ) );

            $tags = array(
                'TURPENTINE_ESI_BLOCK',
                'TURPENTINE_ESI_CACHETYPE_' . $esiData->getCacheType(),
                'TURPENTINE_ESI_BLOCKTYPE_' . $esiData->getBlockType(),
                'TURPENTINE_ESI_BLOCKNAME_' . $esiData->getNameInLayout(),
            );
            Mage::log( 'Saving ESI block: ' . $esiDataHash );
            Mage::app()->getCache()->save( serialize( $esiData ),
                $esiDataHash, $tags, null );
            $blockObject->setEsiData( $esiData );

            //flag request for ESI processing
            Mage::getSingleton( 'turpentine/sentinel' )->setEsiFlag( true );
        } // else handle the block like normal and cache it inline with the page
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
        $esiData->setCacheType( $esiOptions['cache_type'] );
        if( isset( $esiOptions['ttl'] ) ) {
            $esiData->setTtl( $esiOptions['ttl'] );
        } else {
            switch( $esiOptions['cache_type'] ) {
                case 'global':
                    $ttlKey = 'turpentine_vcl/ttls/esi_global';
                    break;
                case 'per-page':
                    $ttlKey = 'turpentine_vcl/ttls/esi_per_page';
                    $esiData->setParentUrl( Mage::app()->getRequest()
                        ->getRequestString() );
                    break;
                case 'per-client':
                    $ttlKey = 'turpentine_vcl/ttls/esi_per_client';
                    //TODO: may need to set session id like parent url
                    break;
                default:
                    Mage::throwException( 'Invalid block cache_type: ' .
                        $esiOptions['cache_type'] );
            }
            $esiData->setTtl( Mage::getStoreConfig( $ttlKey ) );
        }
        $esiData->setBlockType( get_class( $blockObject ) );
        return $esiData;
    }

    protected function _getEsiDebugId( $esiDataHash ) {
        return sha1( $this->_getHashSalt() . $esiDataHash . microtime() );
    }

    protected function _getEsiDataHash( $esiData ) {
        $hashData = $esiData->toArray();
        ksort( $hashData );
        return sha1( $this->_getHashSalt() . serialize( $hashData ) );
    }

    protected function _getHashSalt() {
        return Mage::helper( 'core' )->encrypt(
            Mage::getStoreConfig( 'turpentine_varnish/servers/auth_key' ) );
    }
}
