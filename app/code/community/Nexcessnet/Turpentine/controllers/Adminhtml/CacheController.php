<?php
/**
 * User: damian.pastorini@usestrategery.com
 * Date: 08/01/14
 */

require_once Mage::getModuleDir('controllers', 'Mage_Adminhtml').DS.'CacheController.php';

class Nexcessnet_Turpentine_Adminhtml_CacheController extends Mage_Adminhtml_CacheController
{

    /**
     * Mass action for cache enabeling
     */
    public function massEnableAction()
    {
        $types = $this->getRequest()->getParam('types');
        $allTypes = Mage::app()->useCache();

        $updatedTypes = 0;
        foreach ($types as $code) {
            if (empty($allTypes[$code])) {
                $allTypes[$code] = 1;
                $updatedTypes++;
            }
        }
        if ($updatedTypes > 0) {
            // disable FPC when Varnish cache is enabled:
            if($allTypes['turpentine_pages']==1 || $allTypes['turpentine_esi_blocks']==1)
            {
                $allTypes['full_page'] = 0;
		Mage::getSingleton('core/session')->addSuccess(Mage::helper('adminhtml')->__("Full page cache has been disabled since Varnish cache is enabled."));
            } else if ($allTypes['full_page']==1) {
	        Mage::getSingleton('core/session')->addSuccess(Mage::helper('adminhtml')->__("Turpentine cache has been disabled since Full Page cache is enabled."));
	    }
            // disable FPC when Varnish cache is enabled.
            Mage::app()->saveUseCache($allTypes);
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__("%s cache type(s) enabled.", $updatedTypes));
        }
        $this->_redirect('*/*');
    }

}
