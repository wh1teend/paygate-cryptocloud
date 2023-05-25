<?php

namespace WH1\PaygateCryptoCloud;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	public function installStep1(): void
	{
		$db = $this->db();

		$db->insert('xf_payment_provider', [
			'provider_id'    => "wh1CryptoCloud",
			'provider_class' => "WH1\\PaygateCryptoCloud:CryptoCloud",
			'addon_id'       => "WH1/PaygateCryptoCloud"
		]);
	}

	public function uninstallStep1()
	{
		$db = $this->db();

		$db->delete('xf_payment_profile', "provider_id = 'wh1CryptoCloud'");
		$db->delete('xf_payment_provider', "provider_id = 'wh1CryptoCloud'");
	}
}