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
     * Allows disabling page-caching by setting the cache flag on a controller
     *
     *   <customer_account>
     *     <turpentine_cache_flag value="0" />
     *   </customer_account>
     *
     * Events: controller_action_layout_generate_blocks_after
     *
     * @param  [type] $eventObject [description]
     * @return null
     */
    public function checkCacheFlag( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            Mage::log( 'Checking Varnish cache flag' );
            $layoutXml = $eventObject->getLayout()->getUpdate()->asSimplexml();
            foreach( $layoutXml->xpath( '//turpentine_cache_flag' ) as $node ) {
                foreach( $node->attributes() as $attr => $value ) {
                    if( $attr == 'value' ) {
                        if( !(string)$value ) {
                            Mage::log( 'Disabling Varnish cache for request' );
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
     * On controller redirects, check the target URL and set to home page
     * if it would otherwise go to a getBlock URL
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function checkRedirectUrl( $eventObject ) {
        $url = $eventObject->getTransport()->getUrl();
        $getBlockUrlPattern = sprintf( '~%s~',
            preg_quote( Mage::getUrl( 'turpentine/esi/getBlock' ), '~' ) );
        if( preg_match( $getBlockUrlPattern, $url ) ) {
            $eventObject->getTransport()->setUrl(
                Mage::getBaseUrl() );
        }
    }

    /**
     * Cache block content then replace with ESI template
     *
     * TODO: this could possible be sped up by checking if the block is already
     * in the cache
     *
     * ESI blocks can be designated by adding to:
     *     app/design/frontend/default/default/layout/turpentine_esi_custom.xml
     *
     *     <layout version="0.1.0">
     *         <$CONTROLLER_NAME>
     *             <reference name="$BLOCK_NAME">
     *                 <action method="setEsi">
     *                     <params>
     *                         <cache_type>per-client</cache_type>
     *                         <ttl>120</ttl>
     *                         <registry_keys>$KEY1,$KEY2</registry_keys>
     *                     </params>
     *                 </action>
     *             </reference>
     *         </$CONTROLLER_NAME>
     *     </layout>
     *
     * The params are optional. Valid cache_types are:
     *     global, per-page, and per-client
     * TTLs are in seconds, registry_keys should be a comma-separated list of
     * keys to preserve in the cache, that will be needed to do the actual
     * rendering of the block.
     *
     * Events: core_block_abstract_to_html_before
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function injectEsi( $eventObject ) {
        $blockObject = $eventObject->getBlock();
        /* very spammy and slow, but useful for debugging
        if( $blockObject instanceof Mage_Core_Block_Template ) {
            Mage::log( 'BLOCK: ' . $blockObject->getNameInLayout() );
        }
        */
        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $esiOptions = $blockObject->getEsi() ) {
            if( Mage::app()->getStore()->getCode() == 'admin' ) {
                //admin blocks are not allowed to be cached for now
                Mage::log( 'Erroneous attempt to ESI inject adminhtml block: ' .
                    $blockObject->getNameInLayout(), Zend_Log::WARN );
                return;
            }
            //change the block's template to the stripped down ESI template
            $blockObject->setTemplate( 'turpentine/esi.phtml' );
            $esiData = $this->_getEsiData( $blockObject, $esiOptions );
            //get this now so we don't include stuff added later
            $esiDataHash = $this->_getEsiDataHash( $esiData );
            $esiData->setDebugId( $this->_getEsiDebugId( $esiDataHash ) );
            //save the requested registry keys
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
                'TURPENTINE_ESI_CLIENTID_' . Mage::getSingleton( 'core/session',
                    array( 'name' => 'frontend' ) )
                    ->getSessionId(),
            );
            Mage::log( sprintf( 'Saving ESI block: %s -> %s',
                $esiData->getNameInLayout(), $esiDataHash ) );
            Mage::app()->getCache()->save( serialize( $esiData ),
                $esiDataHash, $tags, null );
            $blockObject->setEsiData( $esiData );

            //flag request for ESI processing
            Mage::getSingleton( 'turpentine/sentinel' )->setEsiFlag( true );
        } // else handle the block like normal and cache it inline with the page
    }

    /**
     * Generate ESI data used in hash
     *
     * @param  Mage_Core_Block_Template $blockObject
     * @param  array $esiOptions
     * @return Varien_Object
     */
    protected function _getEsiData( $blockObject, $esiOptions ) {
        $esiOptions = array_merge(
            array(
                'cache_type'    => 'global',
            ),
            $esiOptions );

        $esiData = new Varien_Object();
        //store stuff used in the hash
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

    /**
     * Generate the block debug ID to identify ESI blocks in output
     *
     * @param  string $esiDataHash ESI block hash
     * @return string
     */
    protected function _getEsiDebugId( $esiDataHash ) {
        return sha1( $this->_getHashSalt() . $esiDataHash . microtime() );
    }

    /**
     * Generate the block hash for cache ID
     *
     * @param  Varien_Object $esiData
     * @return string
     */
    protected function _getEsiDataHash( $esiData ) {
        $hashData = $esiData->toArray();
        ksort( $hashData );
        return sha1( $this->_getHashSalt() . serialize( $hashData ) );
    }

    /**
     * Get the salt used for generating hashes
     *
     * @return string
     */
    protected function _getHashSalt() {
        return Mage::helper( 'core' )->encrypt(
            Mage::getStoreConfig( 'turpentine_varnish/servers/auth_key' ) );
    }
}
