<?php

class Nexcessnet_Turpentine_Block_Management
    extends Mage_Adminhtml_Block_Template {

    public function __construct() {
        $this->_controller = 'management';
        $this->setTemplate( 'turpentine/varnish_management.phtml' );
        parent::__construct();
    }

    /**
     * Get the flushAll url
     *
     * @return string
     */
    public function getFlushAllUrl() {
        return $this->getUrl( '*/*/flushAll' );
    }

    /**
     * Get the flushPartial URL
     *
     * @return string
     */
    public function getFlushPartialUrl() {
        return $this->getUrl( '*/*/flushPartial' );
    }

    /**
     * Get the flushContentType URL
     *
     * @return string
     */
    public function getFlushContentTypeUrl() {
        return $this->getUrl( '*/*/flushContentType' );
    }

    /**
     * Get the applyConfig URL
     *
     * @return string
     */
    public function getApplyConfigUrl() {
        return $this->getUrl( '*/*/applyConfig' );
    }

    /**
     * Get the saveConfig URL
     *
     * @return string
     */
    public function getSaveConfigUrl() {
        return $this->getUrl('*/*/saveConfig');
    }

    /**
     * Get the getConfig URL
     *
     * @return string
     */
    public function getGetConfigUrl() {
        return $this->getUrl('*/*/getConfig');
    }
}
