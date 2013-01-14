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

/**
 * Most of this is taken from @link https://github.com/huguesalary/Magento-Varnish
 */

class Nexcessnet_Turpentine_Model_Observer_Ban extends Varien_Event_Observer {

    /**
     * Cache the varnish admin object
     * @var Nexcessnet_Turpentine_Model_Varnish_Admin
     */
    protected $_varnishAdmin    = null;
    /**
     * Flag to prevent doing the ESI cache clear more than once per request
     * @var boolean
     */
    protected $_esiClearFlag    = false;

    /**
     * Clear the ESI block cache for a specific client
     *
     * Events:
     *     checkout_cart_add_product_complete
     *     checkout_cart_after_save
     *     checkout_onepage_controller_success_action
     *     controller_action_postdispatch_checkout_cart_couponpost
     *     core_session_abstract_add_message
     *     catalog_product_compare_add_product
     *     sales_quote_save_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banClientEsiCache( $eventObject ) {
        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                !$this->_esiClearFlag ) {
            $sessionId = Mage::app()->getRequest()->getCookie( 'frontend' );
            if( $sessionId ) {
                $result = $this->_getVarnishAdmin()->flushExpression(
                    'obj.http.X-Varnish-Session', '~', $sessionId );
                Mage::dispatchEvent( 'turpentine_ban_client_esi_cache', $result );
                if( $this->_checkResult( $result ) ) {
                    if( Mage::helper( 'turpentine/esi' )->getEsiDebugEnabled() ) {
                        Mage::log( 'Cleared Varnish ESI cache for client: ' . $sessionId );
                    }
                } else {
                    Mage::log( 'Failed to clear Varnish ESI cache for client: ' .
                        $sessionId, Zend_Log::WARN );
                }
            }
            $this->_esiClearFlag = true;
        }
    }

    /**
     * Ban a specific product page from the cache
     *
     * Events:
     *     catalog_product_save_commit_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banProductPageCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $product = $eventObject->getProduct();
            $result = $this->_getVarnishAdmin()->flushUrl( $product->getUrlKey() );
            Mage::dispatchEvent( 'turpentine_ban_product_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                    $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addProductToCrawlerQueue( $product );
            }
        }
    }

    /**
     * Ban a product page from the cache if it's stock status changed
     *
     * Events:
     *     cataloginventory_stock_item_save_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banProductPageCacheCheckStock( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $item = $eventObject->getItem();
            if( $item->getStockStatusChangedAuto() ||
                    ( $item->getOriginalInventoryQty() <= 0 &&
                        $item->getQty() > 0 &&
                        $item->getQtyCorrection() > 0 ) ) {
                $cronHelper = Mage::helper( 'turpentine/cron' );
                $parentIds = array_merge(
                    Mage::getModel( 'catalog/product_type_configurable' )
                        ->getParentIdsByChild( $item->getProductId() ),
                    Mage::getModel( 'catalog/product_type_grouped' )
                        ->getParentIdsByChild( $item->getProductId() ) );
                $urlPatterns = array();
                foreach( $parentIds as $parentId ) {
                    $parentProduct = Mage::getModel( 'catalog/product' )
                        ->load( $parentId );
                    $urlPatterns[] = $parentProduct->getUrlKey();
                    if( $cronHelper->getCrawlerEnabled() ) {
                        $cronHelper->addProductToCrawlerQueue( $parentProduct );
                    }
                }
                $product = Mage::getModel( 'catalog/product' )
                    ->load( $item->getProductId() );
                $urlPatterns[] = $product->getUrlKey();
                $pattern = sprintf( '(?:%s)', implode( '|', $urlPatterns ) );
                $result = $this->_getVarnishAdmin()->flushUrl( $pattern );
                Mage::dispatchEvent( 'turpentine_ban_product_cache_check_stock',
                    $result );
                if( $this->_checkResult( $result ) &&
                        $cronHelper->getCrawlerEnabled() ) {
                    $cronHelper->addProductToCrawlerQueue( $product );
                }
            }
        }
    }

    /**
     * Ban a category page, and any subpages on save
     *
     * Events:
     *     catalog_category_save_commit_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banCategoryCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $category = $eventObject->getCategory();
            $result = $this->_getVarnishAdmin()->flushUrl( $category->getUrlKey() );
            Mage::dispatchEvent( 'turpentine_ban_category_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                    $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addCategoryToCrawlerQueue( $category );
            }
        }
    }

    /**
     * Clear the media (CSS/JS) cache, corresponds to the buttons on the cache
     * page in admin
     *
     * Events:
     *     clean_media_cache_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banMediaCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushUrl( 'media/(?:js|css)/' );
            Mage::dispatchEvent( 'turpentine_ban_media_cache', $result );
            $this->_checkResult( $result );
        }
    }

    /**
     * Flush catalog images cache, corresponds to same button in admin cache
     * management page
     *
     * Events:
     *     clean_catalog_images_cache_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banCatalogImagesCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushUrl(
                'media/catalog/product/cache/' );
            Mage::dispatchEvent( 'turpentine_ban_catalog_images_cache', $result );
            $this->_checkResult( $result );
        }
    }

    /**
     * Ban a specific CMS page from cache after edit
     *
     * Events:
     *     cms_page_save_commit_after
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banCmsPageCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $pageId = $eventObject->getDataObject()->getIdentifier();
            $result = $this->_getVarnishAdmin()->flushUrl( $pageId . '\.html$' );
            Mage::dispatchEvent( 'turpentine_ban_cms_page_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                    $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addCmsPageToCrawlerQueue( $pageId );
            }
        }
    }

    /**
     * Do a full cache flush, corresponds to "Flush Magento Cache" and
     * "Flush Cache Storage" buttons in admin > cache management
     *
     * Events:
     *     adminhtml_cache_flush_system
     *     adminhtml_cache_flush_all
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banAllCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushAll();
            Mage::dispatchEvent( 'turpentine_ban_all_cache', $result );
            $this->_checkResult( $result );
        }
    }

    /**
     * Do a flush on the ESI blocks
     *
     * Events:
     *     adminhtml_cache_refresh_type
     *
     * @param  [type] $eventObject [description]
     * @return [type]
     */
    public function banCacheType( $eventObject ) {
        switch( $eventObject->getType() ) {
            //note this is the name of the container xml tag in config.xml,
            // **NOT** the cache tag name
            case Mage::helper( 'turpentine/esi' )->getMageCacheName():
                if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() ) {
                    $result = $this->_getVarnishAdmin()->flushUrl(
                        '/turpentine/esi/getBlock/' );
                    Mage::dispatchEvent( 'turpentine_ban_esi_cache', $result );
                    $this->_checkResult( $result );
                }
                break;
            case Mage::helper( 'turpentine/varnish' )->getMageCacheName():
                $this->banAllCache( $eventObject );
                break;
        }
    }

    /**
     * Check a result from varnish admin action, log if result has errors
     *
     * @param  array $result stored as $socketName => $result
     * @return bool
     */
    protected function _checkResult( $result ) {
        $rvalue = true;
        foreach( $result as $socketName => $value ) {
            if( $value !== true ) {
                Mage::log( sprintf(
                    'Error in Varnish action result for server [%s]: %s',
                        $socketName, $value ),
                    Zend_Log::WARN );
                $rvalue = false;
            }
        }
        return $rvalue;
    }

    /**
     * Get the varnish admin socket
     *
     * @return Nexcessnet_Turpentine_Model_Varnish_Admin
     */
    protected function _getVarnishAdmin() {
        if( is_null( $this->_varnishAdmin ) ) {
            $this->_varnishAdmin = Mage::getModel( 'turpentine/varnish_admin' );
        }
        return $this->_varnishAdmin;
    }
}
