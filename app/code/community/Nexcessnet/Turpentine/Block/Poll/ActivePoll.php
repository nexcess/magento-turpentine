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

class Nexcessnet_Turpentine_Block_Poll_ActivePoll extends Mage_Poll_Block_ActivePoll {

	public function setTemplate($template)
	{
		if ((Mage::getConfig()->getModuleConfig('Mage_Poll')->is('active', 'true')) &&
		(!Mage::getStoreConfig('advanced/modules_disable_output/Mage_Poll')))
		{
			$this->_template = $template;
			$this->setPollTemplate('turpentine/ajax.phtml', 'poll');
			$this->setPollTemplate('turpentine/ajax.phtml', 'results');
		}
		else
		{
			// Mage_Poll is disabled, so do nothing
		}
		return $this;
	}
}
