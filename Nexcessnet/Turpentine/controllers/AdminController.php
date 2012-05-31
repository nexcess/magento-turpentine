<?php

class Nexcessnet_Turpentine_AdminController extends Mage_Adminhtml_Controller_Action {
    public function indexAction() {
        $this->loadLayout()
            ->_addContent($this->getLayout()->createBlock('turpentine/admin_main'))
            ->renderLayout();
    }
}
