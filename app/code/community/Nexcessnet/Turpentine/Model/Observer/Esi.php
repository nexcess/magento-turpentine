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
            $response->setHeader( 'X-Turpentine-Esi',
                $sentinel->getEsiFlag() ? '1' : '0' );
            if( Mage::helper( 'turpentine/esi' )->getEsiDebugEnabled() ) {
                Mage::log( 'Set ESI flag header to: ' .
                    ( $sentinel->getEsiFlag() ? '1' : '0' ) );
            }
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
            $layoutXml = $eventObject->getLayout()->getUpdate()->asSimplexml();
            foreach( $layoutXml->xpath( '//turpentine_cache_flag' ) as $node ) {
                foreach( $node->attributes() as $attr => $value ) {
                    if( $attr == 'value' ) {
                        if( !(string)$value ) {
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
        $getBlockUrlPattern = '~/turpentine/esi/getBlock/~';
        if( preg_match( $getBlockUrlPattern, $url ) ) {
            if( Mage::helper( 'turpentine/esi' )->getEsiDebugEnabled() ) {
                Mage::log( 'Caught redirect to ESI getBlock URL, intercepting' );
            }
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
     *                 <action method="setEsiOptions">
     *                     <params>
     *                         <cacheType>per-client</cacheType>
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
        if( (bool)Mage::getStoreConfig(
                'turpentine_varnish/general/block_debug' ) ) {
            Mage::log( 'Checking ESI block candidate: ' .
                $blockObject->getNameInLayout() );
        }
        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $esiOptions = $blockObject->getEsiOptions() ) {
            if( Mage::app()->getStore()->getCode() == 'admin' ) {
                //admin blocks are not allowed to be cached for now
                Mage::log( 'Ignoring attempt to ESI inject adminhtml block: ' .
                    $blockObject->getNameInLayout(), Zend_Log::WARN );
                return;
            }
            $ttlParam = Mage::helper( 'turpentine/esi' )->getEsiTtlParam();
            $cacheTypeParam = Mage::helper( 'turpentine/esi' )
                ->getEsiCacheTypeParam();
            $dataParam = Mage::helper( 'turpentine/esi' )->getEsiDataParam();
            $esiOptions = array_merge( $this->_getDefaultEsiOptions(),
                $esiOptions );
            if( !isset( $esiOptions[$ttlParam] ) ) {
                //set default esi ttl by cache type
                switch( $esiOptions[$cacheTypeParam] ) {
                    case 'global':
                        $ttlKey = 'turpentine_vcl/ttls/esi_global';
                        break;
                    case 'per-page':
                        $ttlKey = 'turpentine_vcl/ttls/esi_per_page';
                        break;
                    case 'per-client':
                        $ttlKey = 'turpentine_vcl/ttls/esi_per_client';
                        //TODO: may need to set session id like parent url
                        break;
                    default:
                        Mage::throwException( 'Invalid block cache_type: ' .
                            $esiOptions[$cacheTypeParam] );
                }
                $esiOptions[$ttlParam] = Mage::getStoreConfig( $ttlKey );
            }
            //change the block's template to the stripped down ESI template
            $blockObject->setTemplate( 'turpentine/esi.phtml' );
            //esi data is the data needed to regenerate the ESI'd block
            $esiData = $this->_getEsiData( $blockObject, $esiOptions )->toArray();
            ksort( $esiData );

            $esiUrl = Mage::getUrl( 'turpentine/esi/getBlock', array(
                $cacheTypeParam => $esiOptions[$cacheTypeParam],
                $ttlParam       => $esiOptions[$ttlParam],
                //we probably don't really need to encrypt this but it doesn't hurt
                $dataParam      => base64_encode( Mage::helper( 'core' )
                                    ->encrypt( serialize( $esiData ) ) ),
            ) );
            $blockObject->setEsiUrl( $esiUrl );
            if( strlen( $esiUrl ) > 2047 ) {
                Mage::log( 'ESI url is probably to long (> 2047 characters): ' .
                    $esiOptions['url'], Zend_Log::WARN );
            }

            //flag request for ESI processing
            Mage::getSingleton( 'turpentine/sentinel' )->setEsiFlag( true );
        } // else handle the block like normal and cache it inline with the page
    }

    /**
     * Generate ESI data to be encoded in URL
     *
     * @param  Mage_Core_Block_Template $blockObject
     * @param  array $esiOptions
     * @return Varien_Object
     */
    protected function _getEsiData( $blockObject, $esiOptions ) {
        $cacheTypeParam = Mage::helper( 'turpentine/esi' )
            ->getEsiCacheTypeParam();
        $esiData = new Varien_Object();
        $esiData->setStoreId( Mage::app()->getStore()->getId() );
        $esiData->setDesignPackage( Mage::getDesign()->getPackageName() );
        $esiData->setDesignTheme( Mage::getDesign()->getTheme( 'layout' ) );
        $esiData->setNameInLayout( $blockObject->getNameInLayout() );
        $esiData->setBlockType( get_class( $blockObject ) );
        if( $esiOptions[$cacheTypeParam] == 'per-page' ) {
            $esiData->setParentUrl( Mage::app()->getRequest()->getRequestString() );
        }
        $esiData->setDummyBlocks( Mage::helper( 'turpentine/data' )
            ->cleanExplode( ',', $esiOptions['dummy_blocks'] ) );
        $registryKeys = Mage::helper( 'turpentine/data' )
            ->cleanExplode( ',', $esiOptions['registry_keys'] );
        if( count( $registryKeys ) > 0 ) {
            $registry = array_combine(
                $registryKeys,
                array_map( array( 'Mage', 'registry' ), $registryKeys ) );
            if( !$registry ) {
                Mage::log( 'Failed to populate ESI data registry', Zend_Log::WARN );
                $registry = array();
            }
        } else {
            $registry = array();
        }
        //save the requested registry keys
        $esiData->setRegistry( $registry );
        return $esiData;
    }

    /**
     * Get the default ESI options
     *
     * @return array
     */
    protected function _getDefaultEsiOptions() {
        return array(
            'dummy_blocks'      => '',
            Mage::helper( 'turpentine/esi' )->getEsiCacheTypeParam()
                                => 'per-client',
            'registry_keys'     => '',
        );
    }
}
