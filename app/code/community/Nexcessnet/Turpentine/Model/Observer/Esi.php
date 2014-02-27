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
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function setFlagHeaders( $eventObject ) {
        $response = $eventObject->getResponse();
        if( Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ) {
            $response->setHeader( 'X-Turpentine-Esi',
                Mage::registry( 'turpentine_esi_flag' ) ? '1' : '0' );
            Mage::helper( 'turpentine/debug' )->logDebug(
                'Set ESI flag header to: %s',
                ( Mage::registry( 'turpentine_esi_flag' ) ? '1' : '0' ) );
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
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $url = $eventObject->getTransport()->getUrl();
        $referer = Mage::helper( 'core/http' )->getHttpReferer();
        $dummyUrl = $esiHelper->getDummyUrl();
        $reqUenc = Mage::helper( 'core' )->urlDecode(
            Mage::app()->getRequest()->getParam( 'uenc' ) );

        if( $this->_checkIsEsiUrl( $url ) ) {
            if( $this->_checkIsNotEsiUrl( $reqUenc ) &&
                    Mage::getBaseUrl() == $url ) {
                $newUrl = $this->_fixupUencUrl( $reqUenc );
            } elseif( $this->_checkIsNotEsiUrl( $referer ) ) {
                $newUrl = $referer;
            } else {
                $newUrl = $dummyUrl;
            }
            // TODO: make sure this actually looks like a URL
            $eventObject->getTransport()->setUrl( $newUrl );
        }

        if( $eventObject->getTransport()->getUrl() != $url ) {
            Mage::helper( 'turpentine/debug' )->logDebug(
                'Detected redirect to ESI URL, changing: %s => %s',
                $url, $eventObject->getTransport()->getUrl() );
        }
    }

    /**
     * Load the cache clear events from stored config
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function loadCacheClearEvents( $eventObject ) {
        Varien_Profiler::start( 'turpentine::observer::esi::loadCacheClearEvents' );
        $events = Mage::helper( 'turpentine/esi' )->getCacheClearEvents();
        $appShim = Mage::getSingleton( 'turpentine/shim_mage_core_app' );
        foreach( $events as $ccEvent ) {
            $appShim->shim_addEventObserver( 'global', $ccEvent,
                'turpentine_ban_' . $ccEvent, 'singleton',
                'turpentine/observer_ban', 'banClientEsiCache' );
        }
        Varien_Profiler::stop( 'turpentine::observer::esi::loadCacheClearEvents' );
    }

    /**
     * Add the core/messages block rewrite if the flash message fix is enabled
     *
     * The core/messages block is rewritten because it doesn't use a template
     * we can replace with an ESI include tag, just dumps out a block of
     * hard-coded HTML and also frequently skips the toHtml method
     *
     * @param Varien_Object $eventObject
     * @return null
     */
    public function addMessagesBlockRewrite( $eventObject ) {
        if( Mage::helper( 'turpentine/esi' )->shouldFixFlashMessages() ) {
            Varien_Profiler::start( 'turpentine::observer::esi::addMessagesBlockRewrite' );
            Mage::getSingleton( 'turpentine/shim_mage_core_app' )
                ->shim_addClassRewrite( 'block', 'core', 'messages',
                    'Nexcessnet_Turpentine_Block_Core_Messages' );
            Varien_Profiler::stop( 'turpentine::observer::esi::addMessagesBlockRewrite' );
        }
    }

    /**
     * Check the magento version and runtime env and set the replace_form_key
     * flag if needed
     *
     * @param Varien_Object $eventObject
     * @return null
     */
    public function setReplaceFormKeyFlag( $eventObject ) {
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $varnishHelper = Mage::helper( 'turpentine/varnish' );
        $request = Mage::app()->getRequest();
        if( $esiHelper->shouldResponseUseEsi() &&
                $varnishHelper->csrfFixupNeeded() &&
                !$request->isPost() ) {
            Mage::register( 'replace_form_key', true );
        }
    }

    /**
     * Replace the form key placeholder with the ESI include fragment
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function replaceFormKeyPlaceholder( $eventObject ) {
        if( Mage::registry( 'replace_form_key' ) ) {
            $esiHelper = Mage::helper( 'turpentine/esi' );
            $response = $eventObject->getResponse();
            $responseBody = $response->getBody();
            $responseBody = str_replace( '{{form_key_esi_placeholder}}',
                $esiHelper->buildEsiIncludeFragment(
                    $esiHelper->getFormKeyEsiUrl() ),
                $responseBody );
            $response->setBody( $responseBody );
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
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $debugHelper = Mage::helper( 'turpentine/debug' );
        if( $esiHelper->getEsiBlockLogEnabled() ) {
            $debugHelper->logInfo(
                'Checking ESI block candidate: %s',
                $blockObject->getNameInLayout() );
        }
        if( $esiHelper->shouldResponseUseEsi() &&
                $blockObject instanceof Mage_Core_Block_Template &&
                $esiOptions = $blockObject->getEsiOptions() ) {
            if( Mage::app()->getStore()->getCode() == 'admin' ) {
                // admin blocks are not allowed to be cached for now
                $debugHelper->logWarn(
                    'Ignoring attempt to inject adminhtml block: %s',
                    $blockObject->getNameInLayout() );
                return;
            } elseif( $esiHelper->getEsiBlockLogEnabled() ) {
                $debugHelper->logInfo( 'Block check passed, injecting block: %s',
                    $blockObject->getNameInLayout() );
            }
            Varien_Profiler::start( 'turpentine::observer::esi::injectEsi' );
            $ttlParam = $esiHelper->getEsiTtlParam();
            $cacheTypeParam = $esiHelper->getEsiCacheTypeParam();
            $dataParam = $esiHelper->getEsiDataParam();
            $methodParam = $esiHelper->getEsiMethodParam();
            $hmacParam = $esiHelper->getEsiHmacParam();
            $scopeParam = $esiHelper->getEsiScopeParam();
            $referrerParam = $esiHelper->getEsiReferrerParam();

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
            $frozenData = $dataHelper->freeze( $esiData );
            $urlOptions = array(
                $methodParam    => $esiOptions[$methodParam],
                $cacheTypeParam => $esiOptions[$cacheTypeParam],
                $ttlParam       => $esiOptions[$ttlParam],
                $hmacParam      => $dataHelper->getHmac( $frozenData ),
                $dataParam      => $frozenData,
            );
            if( $esiOptions[$methodParam] == 'ajax' ) {
                $urlOptions['_secure'] = Mage::app()->getStore()
                    ->isCurrentlySecure();
            }
            if( $esiOptions[$scopeParam] == 'page' ) {
                $urlOptions[$referrerParam]	= Mage::helper('core')->urlEncode(
                	Mage::getUrl('*/*/*', array('_use_rewrite' => true, '_current' => true))
                );
            }

            $esiUrl = Mage::getUrl( 'turpentine/esi/getBlock', $urlOptions );
            $blockObject->setEsiUrl( $esiUrl );
            // avoid caching the ESI template output to prevent the double-esi-
            // include/"ESI processing not enabled" bug
            foreach( array( 'lifetime', 'tags', 'key' ) as $dataKey ) {
                $blockObject->unsetData( 'cache_' . $dataKey );
            }
            if( strlen( $esiUrl ) > 2047 ) {
                Mage::helper( 'turpentine/debug' )->logWarn(
                    'ESI url is probably too long (%d > 2047 characters): %s',
                    strlen( $esiUrl ), $esiUrl );
            }
            Varien_Profiler::stop( 'turpentine::observer::esi::injectEsi' );
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
        Varien_Profiler::start( 'turpentine::observer::esi::_getEsiData' );
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
        if( $esiOptions[$cacheTypeParam] == 'private' ) {
            if( is_array( @$esiOptions['flush_events'] ) ) {
                $esiData->setFlushEvents( array_merge(
                    $esiHelper->getDefaultCacheClearEvents(),
                    array_keys( $esiOptions['flush_events'] ) ) );
            } else {
                $esiData->setFlushEvents(
                    $esiHelper->getDefaultCacheClearEvents() );
            }
        }
        if( $esiOptions[$scopeParam] == 'page' ) {
            $esiData->setParentUrl( Mage::app()->getRequest()->getRequestString() );
        }
        if( is_array( $esiOptions['dummy_blocks'] ) ) {
            $esiData->setDummyBlocks( $esiOptions['dummy_blocks'] );
        } else {
            Mage::helper( 'turpentine/debug' )->logWarn(
                'Invalid dummy_blocks for block: %s',
                $blockObject->getNameInLayout() );
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
            Mage::helper( 'turpentine/debug' )->logWarn(
                'Invalid registry_keys for block: %s',
                $blockObject->getNameInLayout() );
        }
        $esiData->setSimpleRegistry( $simpleRegistry );
        $esiData->setComplexRegistry( $complexRegistry );
        Varien_Profiler::stop( 'turpentine::observer::esi::_getEsiData' );
        return $esiData;
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
        Varien_Profiler::start( 'turpentine::observer::esi::_getBlockLayoutHandles' );
        $layout = $block->getLayout();
        $layoutXml = Mage::helper( 'turpentine/esi' )->getLayoutXml();
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
                // refer to this block or a child block, unless the handle name
                // is blank
                if( $handle !== '' &&
                    $layoutXml->xpath( sprintf(
                        '//%s//*[@name=\'%s\']', $handle, $blockName ) ) ) {
                    $activeHandles[] = $handle;
                }
            }
        }
        if( !$activeHandles ) {
            $activeHandles[] = 'default';
        }
        Varien_Profiler::stop( 'turpentine::observer::esi::_getBlockLayoutHandles' );
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

    /**
     * Fix a URL to ensure it uses Magento's base URL instead of the backend
     * URL
     *
     * @param  string $uencUrl
     * @return string
     */
    protected function _fixupUencUrl( $uencUrl ) {
        $esiHelper = Mage::helper( 'turpentine/esi' );
        $corsOrigin = $esiHelper->getCorsOrigin();
        if( $corsOrigin != $esiHelper->getCorsOrigin( $uencUrl ) ) {
            return $corsOrigin . parse_url( $uencUrl, PHP_URL_PATH );
        } else {
            return $uencUrl;
        }
    }

    /**
     * Check if a URL *is not* for the /turpentine/esi/getBlock/ action
     *
     * @param  string $url
     * @return bool
     */
    protected function _checkIsNotEsiUrl( $url ) {
        return $url && !preg_match( '~/turpentine/esi/getBlock/~', $url );
    }

    /**
     * Check if a URL *is* for the /turpentine/esi/getBlock/ action
     *
     * @param  string $url
     * @return bool
     */
    protected function _checkIsEsiUrl( $url ) {
        return !$this->_checkIsNotEsiUrl( $url );
    }
}
