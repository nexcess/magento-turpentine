<?php

class Nexcessnet_Turpentine_Block_Test
    extends Mage_Adminhtml_Block_Template {

    public function __construct() {
        $this->_controller = 'management';
        $this->setTemplate('turpentine/varnish_management.phtml');
        parent::__construct();
    }
}
