<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Core
 * @copyright   Copyright (c) 2013 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Core session model
 *
 * @todo extend from Mage_Core_Model_Session_Abstract
 *
 * @method null|bool getCookieShouldBeReceived()
 * @method Mage_Core_Model_Session setCookieShouldBeReceived(bool $flag)
 * @method Mage_Core_Model_Session unsCookieShouldBeReceived()
 */
class Mage_Core_Model_Session extends Mage_Core_Model_Session_Abstract
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
            return $this->real_getFormKey();
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
