<?php

class Nexcessnet_Turpentine_Admin_ControlController extends Mage_Adminhtml_Controller_Action {
    public function indexAction() {
        $this->loadLayout()
            ->_addContent($this->getLayout()->createBlock('turpentine/admin_control_form'))
            ->renderLayout();
    }

    public function postAction() {
        if( $data = $this->getRequest()->getPost() ) {

        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }
}
