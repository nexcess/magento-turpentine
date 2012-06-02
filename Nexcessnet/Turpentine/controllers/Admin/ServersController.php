<?php

class Nexcessnet_Turpentine_Admin_ServersController extends Mage_Adminhtml_Controller_Action {
    public function indexAction() {
        $this->loadLayout()
            ->_addContent(
                $this->getLayout()->createBlock('turpentine/admin_servers_list'))
            ->renderLayout();
    }
}
