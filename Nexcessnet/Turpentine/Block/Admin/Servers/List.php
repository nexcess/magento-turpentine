<?php

class Nexcessnet_Turpentine_Block_Admin_Servers_List
    extends Mage_Adminhtml_Block_Widget_Grid_Container {

    public function __construct() {
        $this->_addButtonLabel = Mage::helper('turpentine')->__('Add Varnish Server');
        parent::__construct();

        $this->_blockGroup = 'turpentine';
        $this->_controller = 'admin_servers';
        $this->_headerText = Mage::helper('turpentine')->__('Varnish Servers');
    }
}
