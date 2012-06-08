<?php

class Nexcessnet_Turpentine_ManagementController
    extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->_title($this->__('System'))
            ->_title(Mage::helper('turpentine')->__('Varnish Management'));
        $this->loadLayout()
            ->_setActiveMenu('system/turpentine')
            ->_addContent($this->getLayout()
                ->createBlock('turpentine/management'))
            ->renderLayout();
    }

    public function flushAllAction() {
        Mage::dispatchEvent('turpentine_varnish_flush_all');
        //flush cache
        $this->_getSession()
            ->addSuccess(Mage::helper('turpentine')
                ->__('The Varnish cache has been flushed.'));
        $this->_redirect('*/*');
    }

    public function flushPartialAction() {
        Mage::dispatchEvent('turpentine_varnish_flush_partial');
        //flush cache
        $url = 'MISSING';
        $this->_getSession()->addSuccess(
            Mage::helper('turpentine')->__('The Varnish cache for (' .
                $url . ') has been flushed.'));
        $this->_redirect('*/*');
    }

    public function saveConfigAction() {
        Mage::dispatchEvent('turpentine_varnish_save_config');
        //save config
        $this->_getSession()
            ->addSuccess(Mage::helper('turpentine')
                ->__('The Varnish config has been saved.'));
        $this->_redirect('*/*');

    }

    public function getConfigAction() {

    }

    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/turpentine');
    }
}
