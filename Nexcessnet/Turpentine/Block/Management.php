<?php

class Nexcessnet_Turpentine_Block_Management
    extends Mage_Adminhtml_Block_Template {

    public function __construct() {
        $this->_controller = 'management';
        $this->setTemplate('turpentine/varnish_management.phtml');
        parent::__construct();
    }

    public function getFlushAllUrl() {
        return $this->getUrl('*/*/flushAll');
    }

    public function getFlushPartialUrl() {
        return $this->getUrl('*/*/flushPartial');
    }

    public function getSaveConfigUrl() {
        return $this->getUrl('*/*/saveConfig');
    }

    public function getGetConfigUrl() {
        return $this->getUrl('*/*/getConfig');
    }
}
