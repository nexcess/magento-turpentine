<?php

class Nexcessnet_Turpentine_Block_Admin_Control_Form
    extends Mage_Adminhtml_Block_Widget_Form {

    protected function _prepareForm() {
        $helper = Mage::helper('turpentine');
        $form = new Varien_Data_Form();

        $enabled = false;

        $fieldset = $form->addFieldset(
            'cache_control',
            array(
                'legend'    => $helper->__('Cache Control'),
            )
        );

        //the big red button
        $fieldset->addField('enable', 'checkbox',
            array(
                'name'  => 'enable',
                'title' => $helper->__('Enable Varnish caching'),
                'label' => $helper->__('Enable Varnish caching'),
                'checked'   => $enabled,
                )
        );

        //url blacklist
        $fieldset->addField('url_blacklist', 'textarea', array(
            'name'      => 'url_blacklist',
            'title'     => $helper->__('URL Blacklist'),
            'label'     => $helper->__('URL Blacklist'),
            'value'     => '/^(?:api|admin)\//',
            'disabled'  => !$enabled,
            'note'      => 'Should be a list of regular expressions, one per line.',
        ));

        //cache ttls
        $fieldset->addField('cache_ttls', 'textarea', array(
            'name'      => 'cache_ttls',
            'title'     => $helper->__('Cache TTLs'),
            'label'     => $helper->__('Cache TTLs'),
            'value'     => '/.*/,3600',
            'disabled'  => !$enabled,
            'note'      => 'A list of regular expressions and TTL in seconds, '.
            'separated with commas; one pair per line.'
        ));

        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('cache_control');
        $form->setAction($this->getUrl('*/*/post'));

        $this->setForm($form);
    }
}
