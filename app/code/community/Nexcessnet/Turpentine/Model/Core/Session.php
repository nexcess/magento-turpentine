<?php

/**
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
class Nexcessnet_Turpentine_Model_Core_Session extends Mage_Core_Model_Session
{
    public function __construct($data=array())
    {
        $name = isset($data['name']) ? $data['name'] : null;
        $this->init('core', $name);
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string A 16 bit unique key for forms
     */
    public function getFormKey()
    {
        if (Mage::registry('replace_form_key') &&
                !Mage::app()->getRequest()->getParam('form_key', false)) {
            // flag request for ESI processing
            Mage::register('turpentine_esi_flag', true, true);
            return '{{form_key_esi_placeholder}}';
        } else {
            return parent::getFormKey();
        }
    }

    public function real_getFormKey()
    {
        if (!$this->getData('_form_key')) {
            $this->setData('_form_key', Mage::helper('core')->getRandomString(16));
        }
        return $this->getData('_form_key');
    }
}
