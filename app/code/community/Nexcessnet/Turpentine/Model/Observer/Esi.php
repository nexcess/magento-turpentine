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
     * Cache for layout XML
     *
     * @var Mage_Core_Model_Layout_Element|SimpleXMLElement
     */
    protected $_layoutXml = null;

    /**
     * Check the ESI flag and set the ESI header if needed
     *
     * Events: http_response_send_before
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function setFlagHeaders( $eventObject ) {
        $response = $eventObject->getResponse();
        if( Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ) {
            $response->setHeader( 'X-Turpentine-Esi',
                Mage::registry( 'turpentine_esi_flag' ) ? '1' : '0' );
            if( Mage::helper( 'turpentine/esi' )->getEsiDebugEnabled() ) {
                Mage::log( 'Set ESI flag header to: ' .
                    ( Mage::registry( 'turpentine_esi_flag' ) ? '1' : '0' ) );
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
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function checkCacheFlag( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->shouldResponseUseVarnish() ) {
            $layoutXml = $eventObject->getLayout()->getUpdate()->asSimplexml();
            foreach( $layoutXml->xpath( '//turpentine_cache_flag' ) as $node ) {
                foreach( $node->attributes() as $attr => $value ) {
                    if( $attr == 'value' ) {
                        if( !(string)$value ) {
                            Mage::register( 'turpentine_nocache_flag', true, true );
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
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function checkRedirectUrl( $eventObject ) {
        $url = $eventObject->getTransport()->getUrl();
        // TODO: make sure this actually looks like a URL
        $reqUenc = Mage::helper( 'core' )->urlDecode(
            Mage::app()->getRequest()->getParam( 'uenc' ) );
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $dummyUrl = $esiHelper->getDummyUrl();
        $getBlockUrlPattern = '~/turpentine/esi/getBlock/~';
        if( preg_match( $getBlockUrlPattern, $url ) ||
                preg_match( $getBlockUrlPattern, $reqUenc ) ) {
            $eventObject->getTransport()->setUrl( $dummyUrl );
        } elseif( $reqUenc && Mage::getBaseUrl() == $url ) {
            $corsOrigin = $esiHelper->getCorsOrigin();
            if( $corsOrigin != $esiHelper->getCorsOrigin( $reqUenc ) ) {
                $eventObject->getTransport()->setUrl(
                    $corsOrigin . parse_url( $reqUenc, PHP_URL_PATH ) );
            }
        }

        if( $eventObject->getTransport()->getUrl() != $url ) {
            if( $esiHelper->getEsiDebugEnabled() ) {
                Mage::log( sprintf(
                    'ESI redirect fixup triggered, rewriting: %s => %s',
                    $url, $eventObject->getTransport()->getUrl() ) );
            }
        }
    }

    /**
     * Load the cache clear events from stored config
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function loadCacheClearEvents( $eventObject ) {
        $events = Mage::helper( 'turpentine/esi' )->getCacheClearEvents();
        $appShim = Mage::getSingleton( 'turpentine/shim_mage_core_app' );
        foreach( $events as $ccEvent ) {
            $appShim->shim_addEventObserver( 'global', $ccEvent,
                'turpentine_ban_' . $ccEvent, 'singleton',
                'turpentine/observer_ban', 'banClientEsiCache' );
        }
    }

    /**
     * Encode block data in URL then replace with ESI template
     *
     * @link https://github.com/nexcess/magento-turpentine/wiki/ESI_Cache_Policy
     *
     * Events: core_block_abstract_to_html_before
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function injectEsi( $eventObject ) {
        $blockObject = $eventObject->getBlock();
        $dataHelper = Mage::helper( 'turpentine/data' );
        $varnishHelper = Mage::helper( 'turpentine/varnish' );
        $esiHelper = Mage::helper( 'turpentine/esi' );
        if( $esiHelper->getEsiBlockLogEnabled() ) {
            Mage::log( 'Checking ESI block candidate: ' .
                $blockObject->getNameInLayout() );
        }
        if( $esiHelper->shouldResponseUseEsi() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $esiOptions = $blockObject->getEsiOptions() ) {
            if( Mage::app()->getStore()->getCode() == 'admin' ) {
                // admin blocks are not allowed to be cached for now
                Mage::log( 'Ignoring attempt to inject adminhtml block: ' .
                    $blockObject->getNameInLayout(), Zend_Log::WARN );
                return;
            }
            $ttlParam = $esiHelper->getEsiTtlParam();
            $cacheTypeParam = $esiHelper->getEsiCacheTypeParam();
            $scopeParam = $esiHelper->getEsiScopeParam();
            $dataParam = $esiHelper->getEsiDataParam();
            $methodParam = $esiHelper->getEsiMethodParam();

            $esiOptions = $this->_getDefaultEsiOptions( $esiOptions );

            // change the block's template to the stripped down ESI template
            switch( $esiOptions[$methodParam] ) {
                case 'ajax':
                    $blockObject->setTemplate( 'turpentine/ajax.phtml' );
                    break;

                case 'esi':
                default:
                    $blockObject->setTemplate( 'turpentine/esi.phtml' );
                    // flag request for ESI processing
                    Mage::register( 'turpentine_esi_flag', true, true );
            }

            // esi data is the data needed to regenerate the ESI'd block
            $esiData = $this->_getEsiData( $blockObject, $esiOptions )->toArray();
            ksort( $esiData );
            $urlOptions = array(
                $methodParam    => $esiOptions[$methodParam],
                $cacheTypeParam => $esiOptions[$cacheTypeParam],
                $ttlParam       => $esiOptions[$ttlParam],
                $dataParam      => $dataHelper->freeze( $esiData ),
            );
            if( $esiOptions[$methodParam] == 'ajax' ) {
                $urlOptions['_secure'] = Mage::app()->getStore()
                    ->isCurrentlySecure();
            }
            $esiUrl = Mage::getUrl( 'turpentine/esi/getBlock', $urlOptions );
            $blockObject->setEsiUrl( $esiUrl );
            // avoid caching the ESI template output to prevent the double-esi-
            // include/"ESI processing not enabled" bug
            foreach( array( 'lifetime', 'tags', 'key' ) as $dataKey ) {
                $blockObject->unsetData( 'cache_' . $dataKey );
            }
            if( strlen( $esiUrl ) > 2047 ) {
                Mage::log( sprintf(
                    'ESI url is probably too long (%d > 2047 characters): %s',
                    strlen( $esiUrl ), $esiUrl ), Zend_Log::WARN );
            }
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
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $cacheTypeParam = $esiHelper->getEsiCacheTypeParam();
        $scopeParam = $esiHelper->getEsiScopeParam();
        $methodParam = $esiHelper->getEsiMethodParam();
        $esiData = new Varien_Object();
        $esiData->setStoreId( Mage::app()->getStore()->getId() );
        $esiData->setDesignPackage( Mage::getDesign()->getPackageName() );
        $esiData->setDesignTheme( Mage::getDesign()->getTheme( 'layout' ) );
        $esiData->setNameInLayout( $blockObject->getNameInLayout() );
        $esiData->setBlockType( get_class( $blockObject ) );
        $esiData->setLayoutHandles( $this->_getBlockLayoutHandles( $blockObject ) );
        $esiData->setEsiMethod( $esiOptions[$methodParam] );
        if( $esiOptions[$scopeParam] == 'page' ) {
            $esiData->setParentUrl( Mage::app()->getRequest()->getRequestString() );
        }
        if( is_array( $esiOptions['dummy_blocks'] ) ) {
            $esiData->setDummyBlocks( $esiOptions['dummy_blocks'] );
        } else {
            Mage::log( 'Invalid dummy_blocks for block: ' .
                $blockObject->getNameInLayout(), Zend_Log::WARN );
        }
        $simpleRegistry = array();
        $complexRegistry = array();
        if( is_array( $esiOptions['registry_keys'] ) ) {
            foreach( $esiOptions['registry_keys'] as $key => $options ) {
                $value = Mage::registry( $key );
                if( $value ) {
                    if( is_object( $value ) &&
                            $value instanceof Mage_Core_Model_Abstract ) {
                        $complexRegistry[$key] =
                            $this->_getComplexRegistryData( $options, $value );
                    } else {
                        $simpleRegistry[$key] = $value;
                    }
                }
            }
        } else {
            Mage::log( 'Invalid registry_keys for block: ' .
                $blockObject->getNameInLayout(), Zend_Log::WARN );
        }
        $esiData->setSimpleRegistry( $simpleRegistry );
        $esiData->setComplexRegistry( $complexRegistry );
        return $esiData;
    }

    /**
     * Get the layout's XML structure
     *
     * This is cached because it's expensive to load for each ESI'd block
     *
     * @return Mage_Core_Model_Layout_Element|SimpleXMLElement
     */
    protected function _getLayoutXml() {
        if( is_null( $this->_layoutXml ) ) {
            if( Mage::app()->useCache( 'layout' ) ) {
                $cacheKey = Mage::helper( 'turpentine/esi' )
                    ->getFileLayoutUpdatesXmlCacheKey();
                $this->_layoutXml = simplexml_load_string(
                    Mage::app()->loadCache( $cacheKey ) );
            }
            // this check is redundant if the layout cache is disabled
            if( !$this->_layoutXml ) {
                $this->_layoutXml = $this->_loadLayoutXml();
            }
            if( Mage::app()->useCache( 'layout' ) ) {
                Mage::app()->saveCache( $this->_layoutXml->asXML(),
                    $cacheKey, array( 'LAYOUT_GENERAL_CACHE_TAG' ) );
            }
        }
        return $this->_layoutXml;
    }

    /**
     * Load the layout's XML structure, bypassing any caching
     *
     * @return Mage_Core_Model_Layout_Element
     */
    protected function _loadLayoutXml() {
        $design = Mage::getDesign();
        $layoutXml = Mage::getSingleton( 'core/layout' )
            ->getUpdate()
            ->getFileLayoutUpdatesXml(
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme( 'layout' ),
                Mage::app()->getStore()->getId() );
        return $layoutXml;
    }

    /**
     * Get the active layout handles for this block and any child blocks
     *
     * This is probably kind of slow since it uses a bunch of xpath searches
     * but this was the easiest way to get the info needed. Should be a target
     * for future optimization
     *
     * There is an issue with encoding the used handles in the URL, if the used
     * handles change (ex customer logs in), the cached version of the page will
     * still have the old handles encoded in it's ESI url. This can lead to
     * weirdness like the "Log in" link displaying for already logged in
     * visitors on pages that were initially visited by not-logged-in visitors.
     * Not sure of a solution for this yet.
     *
     * Above problem is currently solved by EsiController::_swapCustomerHandles()
     * but it would be best to find a more general solution to this.
     *
     * @param  Mage_Core_Block_Template $block
     * @return array
     */
    protected function _getBlockLayoutHandles( $block ) {
        $layout = $block->getLayout();
        $layoutXml = $this->_getLayoutXml();
        $activeHandles = array();
        // get the xml node representing the block we're working on (from the
        // default handle probably)
        $blockNode = current( $layout->getNode()->xpath( sprintf(
            '//block[@name=\'%s\']',
            $block->getNameInLayout() ) ) );
        $childBlocks = Mage::helper( 'turpentine/data' )
            ->getChildBlockNames( $blockNode );
        foreach( $childBlocks as $blockName ) {
            foreach( $layout->getUpdate()->getHandles() as $handle ) {
                // check if this handle has any block or reference tags that
                // refer to this block or a child block
                if( $layoutXml->xpath( sprintf(
                    '//%s//*[@name=\'%s\']', $handle, $blockName ) ) ) {
                    $activeHandles[] = $handle;
                }
            }
        }
        if( !$activeHandles ) {
            $activeHandles[] = 'default';
        }
        return array_unique( $activeHandles );
    }

    /**
     * Get the default ESI options
     *
     * @return array
     */
    protected function _getDefaultEsiOptions( $options ) {
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $ttlParam = $esiHelper->getEsiTtlParam();
        $methodParam = $esiHelper->getEsiMethodParam();
        $cacheTypeParam = $esiHelper->getEsiCacheTypeParam();
        $defaults = array(
            $esiHelper->getEsiMethodParam()         => 'esi',
            $esiHelper->getEsiScopeParam()          => 'global',
            $esiHelper->getEsiCacheTypeParam()      => 'public',
            'dummy_blocks'      => array(),
            'registry_keys'     => array(),
        );
        $options = array_merge( $defaults, $options );

        // set the default TTL
        if( !isset( $options[$ttlParam] ) ) {
            if( $options[$cacheTypeParam] == 'private' ) {
                switch( $options[$methodParam] ) {
                    case 'ajax':
                        $options[$ttlParam] = '0';
                        break;

                    case 'esi':
                    default:
                        $options[$ttlParam] = $esiHelper->getDefaultEsiTtl();
                        break;
                }
            } else {
                $options[$ttlParam] = Mage::helper( 'turpentine/varnish' )
                    ->getDefaultTtl();
            }
        }

        return $options;
    }

    /**
     * Get the complex registry entry data
     *
     * @param  array $valueOptions
     * @param  mixed $value
     * @return array
     */
    protected function _getComplexRegistryData( $valueOptions, $value ) {
        $idMethod = @$valueOptions['id_method'] ?
            $valueOptions['id_method'] : 'getId';
        $model = @$valueOptions['model'] ?
            $valueOptions['model'] : Mage::helper( 'turpentine/data' )
                ->getModelName( $value );
        $data = array(
            'model'         => $model,
            'id'            => $value->{$idMethod}(),
        );
        return $data;
    }
}
