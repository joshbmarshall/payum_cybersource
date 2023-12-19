<?php

namespace Cognito\PayumCybersource\Action;

use Cognito\PayumCybersource\Request\Api\ObtainNonce;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\RenderTemplate;
use Cognito\PayumCybersource\Api;

class ObtainNonceAction implements ActionInterface, GatewayAwareInterface, \Payum\Core\ApiAwareInterface
{
    use GatewayAwareTrait;
    use \Payum\Core\ApiAwareTrait;

    /**
     * @var string
     */
    protected $templateName;

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName)
    {
        $this->templateName = $templateName;
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        /** @var $request ObtainNonce */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if ($model['card'])
        {
            throw new LogicException('The token has already been set.');
        }
        $uri = \League\Uri\Http::createFromServer($_SERVER);

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        // Received payment intent information from Stripe
        if (isset($getHttpRequest->request['payment_intent']))
        {
            $model['nonce'] = $getHttpRequest->request['payment_intent'];
            return;
        }

        // Get JWT

        $targetOrigins = [$uri->withPath('')->withFragment('')->withQuery('')->__toString()];
        $allowedCardNetworks = [
            'VISA',
            'MASTERCARD',
            'AMEX',
        ];

        $requestObjArr = [
            'targetOrigins' => $targetOrigins,
            'clientVersion' => 'v2.0',
            'allowedCardNetworks' => $allowedCardNetworks,
            'checkoutApiInitialization' => [
                'currency' => $model['currency'],
                'amount' => (string)$model['amount'],
            ]
        ];
        $requestObj = new \CyberSource\Model\GenerateCaptureContextRequest($requestObjArr);

        ray($model);

        $config = new \CyberSource\Configuration();
        $config->setHost(str_replace('https://', '', $this->api->getApiEndpoint()));

        $merchantConfig = new \CyberSource\Authentication\Core\MerchantConfiguration();
        $merchantConfig->setMerchantID($model['merchant_id']);
        $merchantConfig->setApiKeyID($model['access_key']);
        $merchantConfig->setSecretKey($model['secret_key']);
        $merchantConfig->setAuthenticationType('HTTP_SIGNATURE');
        $merchantConfig->setUseMetaKey(false);

        $apiClient = new \CyberSource\ApiClient($config, $merchantConfig);
        $apiInstance = new \CyberSource\Api\MicroformIntegrationApi($apiClient);

        try
        {
            $apiResponse = $apiInstance->generateCaptureContext($requestObj);
        }
        catch (\Cybersource\ApiException $e)
        {
            print_r($e->getResponseBody());
            print_r($e->getMessage());
            exit;
        }
        try
        {
            $header = json_decode(base64_decode(substr($apiResponse[0], 0, strpos($apiResponse[0], '.'))), true);
            // Get shared key
            $public_key = json_decode(file_get_contents($this->api->getApiEndpoint() . '/flex/v2/public-keys/' . $header['kid']), true);
            $jwt = \Firebase\JWT\JWT::decode($apiResponse[0], \Firebase\JWT\JWK::parseKeySet(['keys' => [$public_key]]), ['RS256']);
        }
        catch (\Exception $e)
        {
            print_r($e->getMessage());
            exit;
        }
        dump($jwt);

        exit;
        $paymentIntentData = [
            'amount' => round($model['amount'] * pow(10, $model['currencyDigits'])),
            'shipping' => $model['shipping'],
            'currency' => $model['currency'],
            'metadata' => ['integration_check' => 'accept_a_payment'],
            'statement_descriptor' => $model['statement_descriptor_suffix'],
            'description' => $model['description'],
        ];

        $model['stripePaymentIntent'] = \Stripe\PaymentIntent::create($paymentIntentData);
        $payment_element_options = $model['payment_element_options'] ?? (object)[];
        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, array(
            'amount' => $model['currencySymbol'] . ' ' . number_format($model['amount'], $model['currencyDigits']),
            'client_secret' => $model['stripePaymentIntent']->client_secret,
            'publishable_key' => $model['publishable_key'],
            'actionUrl' => $uri->withPath('')->withFragment('')->withQuery('')->__toString() . $getHttpRequest->uri,
            'imgUrl' => $model['img_url'],
            'img2Url' => $model['img_2_url'],
            'payment_element_options' => json_encode($payment_element_options),
            'billing' => $model['billing'] ?? [],
        )));

        throw new HttpResponse($renderTemplate->getResult());
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof ObtainNonce &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
