<?php

class Nexcessnet_Turpentine_Model_Config_Select_Version {
    public function toOptionArray() {
        $helper = Mage::helper('turpentine');
        return array(
            array( 'value' => '2.1', 'label' => $helper->__( '2.1.x' ) ),
            array( 'value' => '3.0', 'label' => $helper->__( '3.0.x' ) ),
        );
    }
}
