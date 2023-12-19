# Cybersource Payment Module

The Payum extension to purchase through Cybersource using Flexible Token

## Install and Use

To install, it's easiest to use composer:

    composer require cognito/payum_cybersource

### Build the config

```php
<?php

use Payum\Core\PayumBuilder;
use Payum\Core\GatewayFactoryInterface;

$defaultConfig = [];

$payum = (new PayumBuilder)
    ->addGatewayFactory('cybersource', function(array $config, GatewayFactoryInterface $coreGatewayFactory) {
        return new \Cognito\PayumCybersource\CybersourceGatewayFactory($config, $coreGatewayFactory);
    })

    ->addGateway('cybersource', [
        'factory' => 'cybersource',
        'merchant_id' => 'Merchant Id',
        'api_key_id' => 'Merchant API Key',
        'secret_key' => 'Merchant Secret Key',
        'img_url' => 'https://path/to/logo/image.jpg',
    ])

    ->getPayum()
;
```

### Request card payment

```php
<?php

use Payum\Core\Request\Capture;

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);
$request = [
    'invoice_id' => 100,
];

$payment = $storage->create();
$payment->setNumber(uniqid());
$payment->setCurrencyCode($currency);
$payment->setTotalAmount(100); // Total cents
$payment->setDescription(substr($description, 0, 45));
$payment->setDetails([
    'local' => [
        'email' => $email, // Used for the customer to be able to save payment details
    ],
    'payment_method_options' => [
        'defaultValues' => [
            'billingDetails' => [
                'name' => $customer_name,
                'email' => $customer_email,
            ], // Optionally prefill some fields for some payment types
        ],
    ],
]);
$storage->setInternalDetails($payment, $request);

$captureToken = $payum->getTokenFactory()->createCaptureToken('cybersource', $payment, 'done.php');
$url = $captureToken->getTargetUrl();
header("Location: " . $url);
die();
```

### Check it worked

```php
<?php
/** @var \Payum\Core\Model\Token $token */
$token = $payum->getHttpRequestVerifier()->verify($request);
$gateway = $payum->getGateway($token->getGatewayName());

/** @var \Payum\Core\Storage\IdentityInterface $identity **/
$identity = $token->getDetails();
$model = $payum->getStorage($identity->getClass())->find($identity);
$gateway->execute($status = new GetHumanStatus($model));

/** @var \Payum\Core\Request\GetHumanStatus $status */

// using shortcut
if ($status->isNew() || $status->isCaptured() || $status->isAuthorized()) {
    // success
} elseif ($status->isPending()) {
    // most likely success, but you have to wait for a push notification.
} elseif ($status->isFailed() || $status->isCanceled()) {
    // the payment has failed or user canceled it.
}
```

## License

Payum Cybersource is released under the [MIT License](LICENSE).
