<?php

class Nexcessnet_Turpentine_Varnish_ManagementController
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
        $vcl = $this->_generateVcl();
        if( strlen( $vcl ) === @file_put_contents( $this->_getVclFilename(), $vcl ) ) {
            $this->_getSession()
                ->addSuccess( Mage::helper('turpentine')
                    ->__('The VCL file has been saved.' ) );
        } else {
            $err = error_get_last();
            $this->_getSession()
                ->addError( Mage::helper('turpentine')
                    ->__('Failed to save the VCL file: ' . $err['message'] ) );
        }
        $this->_redirect('*/*');
    }

    public function getConfigAction() {
        $vcl = $this->_generateVcl();
        $this->getResponse()
            ->setHttpResponseCode( 200 )
            ->setHeader( 'Content-Type', 'text/plain', true )
            ->setHeader( 'Content-Length', strlen( $vcl ) )
            ->setHeader( 'Content-Disposition', 'attachment; filename=' .
                basename( $this->_getVclFilename() ) )
            ->setBody( $vcl );
        return $this;
    }

    protected function _generateVcl() {
        switch( Mage::getStoreConfig( 'turpentine_servers/servers/version' ) ) {
            case '2':
                $cfgr = Mage::getModel( 'turpentine/varnish_configurator_version2' );
                break;
            case '3':
            default:
                $cfgr = Mage::getModel( 'turpentine/varnish_configurator_version3' );
                break;
        }
        $vcl = $cfgr->generate();
        return $vcl;
    }

    protected function _getVclFilename() {
        return str_replace(
            '{{root_dir}}',
            Mage::getBaseDir(),
            Mage::getStoreConfig( 'turpentine_servers/servers/config_file' ) );
    }

    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/turpentine');
    }
}
