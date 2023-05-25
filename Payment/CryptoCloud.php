<?php


namespace WH1\PaygateCryptoCloud\Payment;

use XF;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class CryptoCloud extends AbstractProvider
{
	public function getTitle(): string
	{
		return 'CryptoCloud';
	}

	public function getApiEndpoint(): string
	{
		return 'https://api.cryptocloud.plus/v1/invoice/create';
	}

	public function verifyConfig(array &$options, &$errors = []): bool
	{
		if (empty($options['shop_id']) || empty($options['secret_key']) || empty($options['api_key']))
		{
			$errors[] = XF::phrase('wh1_pg_cryptocloud_you_must_provide_all_data');
		}

		if (!$errors)
		{
			return true;
		}

		return false;
	}

	protected function getPaymentParams(PurchaseRequest $purchaseRequest, Purchase $purchase): array
	{
		$paymentProfileOptions = $purchase->paymentProfile->options;

		$paymentData = [
			'shop_id'    	=> $paymentProfileOptions['shop_id'],
			'order_id'      => $purchaseRequest->request_key,
			'amount'        => $purchase->cost,
			'currency' 		=> $purchase->currency,
            'email'       	=> XF::visitor()->email,
		];

		return $paymentData;
	}

	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): XF\Mvc\Reply\AbstractReply
	{
		$params = $this->getPaymentParams($purchaseRequest, $purchase);

        $profileOptions = $purchase->paymentProfile->options;

        $response = XF::app()->http()->client()->post($this->getApiEndpoint(), [
            'headers' => [
                'Authorization' => 'Token ' . $profileOptions['api_key']
            ],
            'json' => $params,
		]);

		if ($response)
		{
			$responseData = json_decode($response->getBody()->getContents(), true);
			if (!empty($responseData['pay_url']))
			{
				return $controller->redirect($responseData['pay_url']);
			}
		}

		return $controller->error(XF::phrase('something_went_wrong_please_try_again'));
	}

    public function setupCallback(Request $request): CallbackState
	{
		$state = new CallbackState();

        $state->order_id = $request->filter('order_id', 'str'); 
        $state->invoice_id = $request->filter('invoice_id', 'str');
        $state->status = $request->filter('status', 'str');
        $state->amount_crypto = $request->filter('amount_crypto', 'str');
		$state->currency = $request->filter('currency', 'str');
        $state->token = $request->filter('token', 'str');

		$state->_INPUT = array_merge($request->getInputForLogs(), [
			'ip'      => $request->getIp(),
			'referer' => $request->getReferrer()
		]);

        $state->httpCode = 200;

		return $state;
	}

	public function validateCallback(CallbackState $state): bool
	{
		$state->requestKey = $state->order_id ?? '';
        $state->transactionId = $state->invoice_id ?? '';

		if ($state->status != 'success')
		{
			$state->logType = ' ';
			$state->logMessage = ' ';

			return false;
		}

		return parent::validateCallback($state);
	}

	public function validateTransaction(CallbackState $state): bool
	{
		if ($state->transactionId && $state->requestKey)
		{
			return parent::validateTransaction($state);
		}

		$state->logType = 'error';
		$state->logMessage = 'No transaction ID or order ID. No action to take.';

		return false;
	}

	public function validatePurchasableData(CallbackState $state): bool
	{
		$paymentProfile = $state->getPaymentProfile();

		$options = $paymentProfile->options;
		if (!empty($options['secret_key']) && !empty($options['api_key']))
		{
			return true;
		}

		$state->logType = 'error';
		$state->logMessage = 'Invalid public_key or secret_key.';

		return false;
	}

	public function getPaymentResult(CallbackState $state): void
	{
		if ($state->status == 'success')
		{
			$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
		}
	}

	public function prepareLogData(CallbackState $state): void
	{
		$state->logDetails = $state->_INPUT;
	}

	public function supportsRecurring(PaymentProfile $paymentProfile, $unit, $amount, &$result = self::ERR_NO_RECURRING): bool
	{
		$result = self::ERR_NO_RECURRING;

		return false;
	}

	protected $supportedCurrencies = [
		"USD", "RUB", "EUR", "GBP"
	];

	public function verifyCurrency(PaymentProfile $paymentProfile, $currencyCode): bool
	{
		return in_array($currencyCode, $this->supportedCurrencies);
	}
}
