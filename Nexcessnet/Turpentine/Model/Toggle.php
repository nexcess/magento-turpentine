<?php

class Nexcessnet_Turpentine_Model_Toggle {
    public function toOptionArray() {
        $helper = Mage::helper('turpentine');
        return array(
            array( 'value' => true, 'label' => $helper->__( 'On' ) ),
            array( 'value' => false, 'label' => $helper->__( 'Off' ) )
        );
    }
}
