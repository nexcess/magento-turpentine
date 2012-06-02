<?php

class Nexcessnet_Turpentine_Block_Admin_Servers_Grid
    extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->_controller = 'turpentine';
    }

    protected function _prepareCollection() {
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
        return parent::_prepareColumns();
    }

    public function getRowUrl( $row ) {
        return $this->getUrl(
            '*/*/edit',
            array( 'id' => $row->getServerId() )
        );
    }
}
