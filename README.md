# Cybersource Payment Module

The Payum extension to purchase through Cybersource REST API

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
        'factory'         => 'cybersource',
        'organisation_id' => 'Your-organisation-id',
        'key'             => 'Your-key',
        'shared_secret'   => 'Your-shared-secret',
        'sandbox'         => false,
        'img_url'         => 'https://path/to/logo/image.jpg',
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
        'email' => $email,
    ],
    'payment_reference' => 'ABC-123',
    'billing' => [
        'first_name' => $customer['first_name'],
        'last_name'  => $customer['last_name'],
        'email'      => $customer['email'],
        'address'    => [
            'line1'       => $customer_address['line1'],
            'line2'       => $customer_address['line2'],
            'city'        => $customer_address['city'],
            'state'       => $customer_address['state'],
            'country'     => $customer_address['country'],
            'postal_code' => $customer_address['postal_code'],
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

## Getting a key

In your Cybersource dashboard, go to Payment Configuration > Key Management

Click Create Key

Choose REST - Shared Secret

Copy the key and shared secret

## License

Payum Cybersource is released under the [MIT License](LICENSE).
