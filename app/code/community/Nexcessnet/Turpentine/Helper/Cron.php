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

class Nexcessnet_Turpentine_Helper_Cron extends Mage_Core_Helper_Abstract {

    /**
     * Key to store the URL queue under in the cache
     *
     * @var string
     */
    const CRAWLER_URLS_CACHE_ID = 'turpentine_crawler_url_queue';

    /**
     * Crawler client singleton
     *
     * @var Varien_Http_Client
     */
    protected $_crawlerClient = null;

    /**
     * Get the execution time used so far
     *
     * @return int
     */
    public function getRunTime() {
        $usage = getrusage();
        return $usage['ru_utime.tv_sec'];
    }

    /**
     * Get the max execution time (or 0 if unlimited)
     *
     * @return int
     */
    public function getAllowedRunTime() {
        return (int) ini_get('max_execution_time');
    }

    /**
     * Add a single URL to the queue, returns whether it was actually added
     * to the queue or not (false if it was already in the queue)
     *
     * @param string $url
     * @return bool
     */
    public function addUrlToCrawlerQueue($url) {
        return $this->addUrlsToCrawlerQueue(array($url));
    }

    /**
     * Add a list of URLs to the queue, returns how many unique URLs were
     * actually added to the queue
     *
     * @param array $urls
     * @return int
     */
    public function addUrlsToCrawlerQueue(array $urls) {
        // TODO: remove this debug message
        if ($this->getCrawlerDebugEnabled()) {
            foreach ($urls as $url) {
                Mage::helper('turpentine/debug')->log(
                    'Adding URL to queue: %s', $url );
            }
        }
        $oldQueue = $this->_readUrlQueue();
        $newQueue = array_unique(array_merge($oldQueue, $urls));
        $this->_writeUrlQueue($newQueue);
        $diff = count($newQueue) - count($oldQueue);
        return $diff;
    }

    /**
     * Pop a URL to crawl off the queue, or null if no URLs left
     *
     * @return string|null
     */
    public function getNextUrl() {
        $urls = $this->_readUrlQueue();
        $nextUrl = array_shift($urls);
        $this->_writeUrlQueue($urls);
        return $nextUrl;
    }

    /**
     * Get the current URL queue
     *
     * @return array
     */
    public function getUrlQueue() {
        return $this->_readUrlQueue();
    }

    /**
     * Get the crawler http client
     *
     * @return Varien_Http_Client
     */
    public function getCrawlerClient() {
        if (is_null($this->_crawlerClient)) {
            $this->_crawlerClient = new Varien_Http_Client(null, array(
                'useragent'     => sprintf(
                    'Nexcessnet_Turpentine/%s Magento/%s Varien_Http_Client',
                    Mage::helper('turpentine/data')->getVersion(),
                    Mage::getVersion() ),
                'keepalive'     => true,
            ));
            $this->_crawlerClient->setCookie('frontend', 'crawler-session');
        }
        return $this->_crawlerClient;
    }

    /**
     * Get if the crawler is enabled
     *
     * @return bool
     */
    public function getCrawlerEnabled() {
        return Mage::getStoreConfig('turpentine_varnish/general/crawler_enable');
    }

    /**
     * Get if crawler debugging is enabled
     *
     * @return bool
     */
    public function getCrawlerDebugEnabled() {
        return Mage::getStoreConfig('turpentine_varnish/general/crawler_debug');
    }

    /**
     * Get number of urls to crawl per batch
     *
     * @return int
     */
    public function getCrawlerBatchSize() {
        return Mage::getStoreConfig('turpentine_varnish/general/crawler_batchsize');
    }

    /**
     * Get time in seconds to wait between url batches
     *
     * @return int
     */
    public function getCrawlerWaitPeriod() {
        return Mage::getStoreConfig('turpentine_varnish/general/crawler_batchwait');
    }

    /**
     * Get the list of all URLs
     *
     * @return array
     */
    public function getAllUrls() {
        $urls = array();
        $origStore = Mage::app()->getStore();
        $visibility = array(
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
        );
        foreach (Mage::app()->getStores() as $storeId => $store) {
            Mage::app()->setCurrentStore($store);
            $baseUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
            $urls[] = $baseUrl;
            foreach (Mage::getModel('catalog/category')
                        ->getCollection($storeId)
                        ->addIsActiveFilter()
                            as $cat) {
                $urls[] = $cat->getUrl();
                foreach ($cat->getProductCollection($storeId)
                            ->addUrlRewrite($cat->getId())
                            ->addAttributeToFilter('visibility', $visibility)
                                as $prod) {
                    $urls[] = $prod->getProductUrl();
                }
            }
            $sitemap = (Mage::getConfig()->getNode('modules/MageWorx_XSitemap') !== false) ?
                                                           'mageworx_xsitemap/cms_page' : 'sitemap/cms_page';
            foreach (Mage::getResourceModel($sitemap)
                        ->getCollection($storeId) as $item) {
                $urls[] = $baseUrl.$item->getUrl();
            }
        }
        Mage::app()->setCurrentStore($origStore);
        return array_unique($urls);
    }

    /**
     * Add URLs to the queue by product model
     *
     * @param Mage_Catalog_Model_Product $product
     * @return int
     */
    public function addProductToCrawlerQueue($product) {
        $productUrls = array();
        $origStore = Mage::app()->getStore();
        foreach (Mage::app()->getStores() as $storeId => $store) {
            Mage::app()->setCurrentStore($store);
            $baseUrl = $store->getBaseUrl(
                Mage_Core_Model_Store::URL_TYPE_LINK );
            $productUrls[] = $product->getProductUrl();
            foreach ($product->getCategoryIds() as $catId) {
                $cat = Mage::getModel('catalog/category')->load($catId);
                $productUrls[] = rtrim($baseUrl, '/').'/'.
                    ltrim($product->getUrlModel()
                        ->getUrlPath($product, $cat), '/');
            }
        }
        Mage::app()->setCurrentStore($origStore);
        return $this->addUrlsToCrawlerQueue($productUrls);
    }

    /**
     * Add URLs to the queue by category model
     *
     * @param Mage_Catalog_Model_Category $category
     * @return int
     */
    public function addCategoryToCrawlerQueue($category) {
        $catUrls = array();
        $origStore = Mage::app()->getStore();
        foreach (Mage::app()->getStores() as $storeId => $store) {
            Mage::app()->setCurrentStore($store);
            $catUrls[] = $category->getUrl();
        }
        Mage::app()->setCurrentStore($origStore);
        return $this->addUrlsToCrawlerQueue($catUrls);
    }

    /**
     * Add URLs to queue by CMS page ID
     *
     * @param int $cmsPageId
     * @return int
     */
    public function addCmsPageToCrawlerQueue($cmsPageId) {
        $page = Mage::getModel('cms/page')->load($cmsPageId);
        $pageUrls = array();
        $origStore = Mage::app()->getStore();
        foreach (Mage::app()->getStores() as $storeId => $store) {
            Mage::app()->setCurrentStore($store);
            $page->setStoreId($storeId);
            $pageUrls[] = Mage::getUrl(null,
                array('_direct' => $page->getIdentifier()));
        }
        Mage::app()->setCurrentStore($origStore);
        return $this->addUrlsToCrawlerQueue($pageUrls);
    }

    /**
     * Get the crawler URL queue from the cache
     *
     * @return array
     */
    protected function _readUrlQueue() {
        $readQueue = @unserialize(
            Mage::app()->loadCache(self::CRAWLER_URLS_CACHE_ID) );
        if ( ! is_array($readQueue)) {
            // This is the first time the queue has been read since the last
            // cache flush (or the queue is corrupt)
            // Returning an empty array here would be the proper behavior,
            // but causes the queue to not be saved on the full cache flush event
            return $this->getAllUrls();
        } else {
            return $readQueue;
        }
    }

    /**
     * Save the crawler URL queue to the cache
     *
     * @param  array  $urls
     * @return null
     */
    protected function _writeUrlQueue(array $urls) {
        return Mage::app()->saveCache(
            serialize($urls), self::CRAWLER_URLS_CACHE_ID );
    }
}
