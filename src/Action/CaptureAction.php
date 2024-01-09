<?php

namespace Cognito\PayumCybersource\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Cognito\PayumCybersource\Request\Api\ObtainNonce;
use Cognito\PayumCybersource\Api;

class CaptureAction implements ActionInterface, GatewayAwareInterface, \Payum\Core\ApiAwareInterface {
    use GatewayAwareTrait;
    use \Payum\Core\ApiAwareTrait;

    private $config;

    /**
     * @param string $templateName
     */
    public function __construct(ArrayObject $config) {
        $this->config = $config;
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request) {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        if ($model['status']) {
            return;
        }

        $model['merchant_id'] = $this->config['merchant_id'];
        $model['access_key'] = $this->config['access_key'];
        $model['secret_key'] = $this->config['secret_key'];
        $model['img_url'] = $this->config['img_url'] ?? '';
        $model['img_2_url'] = $this->config['img_2_url'] ?? '';

        $obtainNonce = new ObtainNonce($request->getModel());
        $obtainNonce->setModel($model);

        $this->gateway->execute($obtainNonce);

        if (!$model->offsetExists('status')) {
            // Validate the token from the form
            $token = \Firebase\JWT\JWT::decode(str_replace('"', '', $model['nonce']), \Firebase\JWT\JWK::parseKeySet(['keys' => [$model['public_key']]]), ['RS256']);
            // Token ok, attempt to capture funds

            $config = new \CyberSource\Configuration();
            $config->setHost(str_replace('https://', '', $this->api->getApiEndpoint()));

            $merchantConfig = new \CyberSource\Authentication\Core\MerchantConfiguration();
            $merchantConfig->setMerchantID($model['merchant_id']);
            $merchantConfig->setApiKeyID($model['access_key']);
            $merchantConfig->setSecretKey($model['secret_key']);
            $merchantConfig->setAuthenticationType('HTTP_SIGNATURE');
            $merchantConfig->setUseMetaKey(false);

            $api_client = new \CyberSource\ApiClient($config, $merchantConfig);
            $api_instance = new \CyberSource\Api\PaymentsApi($api_client);

            $model['status'] = 'failed';
            $model['error']  = '';
            $model['transactionReference'] = '';
            $status = '';
            try {
                $apiResponse = $api_instance->createPayment(new \CyberSource\Model\CreatePaymentRequest([
                    'clientReferenceInformation' => new \CyberSource\Model\Ptsv2paymentsClientReferenceInformation([
                                'code' => $model['payment_reference'],
                            ]),
                    'orderInformation' => new \CyberSource\Model\Ptsv2paymentsOrderInformation([
                            'amountDetails' => new \CyberSource\Model\Ptsv2paymentsOrderInformationAmountDetails([
                                    'totalAmount' => $model['amount'],
                                    'currency' => $model['currency']
                                ]),
                            'billTo' => new \CyberSource\Model\Ptsv2paymentsOrderInformationBillTo([
                                    'firstName' => $model['billing']['first_name'],
                                    'lastName' => $model['billing']['last_name'],
                                    'address1' => $model['billing']['address']['line1'],
                                    'address2' => $model['billing']['address']['line2'],
                                    'locality' => $model['billing']['address']['city'],
                                    'administrativeArea' => $model['billing']['address']['state'],
                                    'postalCode' => $model['billing']['address']['postal_code'],
                                    'country' => $model['billing']['address']['country'],
                                    'email' => $model['billing']['email'],
                                    //'phoneNumber' => $model['billing']['phone'],
                                ]),
                            ]),
                    'tokenInformation' => new \CyberSource\Model\Ptsv2paymentsTokenInformation([
                                'transientTokenJwt' => $model['nonce']
                            ]),
                    'processingInformation' => new \CyberSource\Model\Ptsv2paymentsProcessingInformation([
                                'capture' => true,
                            ]),
                ]));

                $status = $apiResponse[0]->getStatus();
                if ($status == 'AUTHORIZED') {
                    $model['status'] = 'success';
                } else {
                    $model['error'] = $status . ' ' . $apiResponse[0]->getErrorInformation()->getMessage();
                }
                $model['transactionReference'] = $apiResponse[0]->getId();
                $model['result'] = serialize($apiResponse[0]);
            } catch (\Cybersource\ApiException $e) {
                $model['error'] = $e->getResponseBody()->message . ' ' . json_encode($e->getResponseBody()->details);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request) {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
