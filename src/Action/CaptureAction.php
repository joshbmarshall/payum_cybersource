<?php

namespace Cognito\PayumCybersource\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Cognito\PayumCybersource\Request\Api\ObtainNonce;

class CaptureAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    private $config;

    /**
     * @param string $templateName
     */
    public function __construct(ArrayObject $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        if ($model['status']) {
            return;
        }
        $uri = \League\Uri\Http::fromServer($_SERVER);

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        // Received flex response information from Cybersource
        if (isset($getHttpRequest->request['flexresponse'])) {
            $model['nonce'] = $getHttpRequest->request['flexresponse'];
        }

        $model['organisation_id'] = $this->config['organisation_id'];
        $model['key']             = $this->config['key'];
        $model['shared_secret']   = $this->config['shared_secret'];

        if (!$model->offsetExists('nonce')) {
            // Get server-side capture context
            $contextData = $this->doPostRequest('/microform/v2/sessions', [
                'clientVersion'       => 'v2',
                'targetOrigins'       => [$uri->getScheme() . '://' . $uri->getHost()],
                'allowedCardNetworks' => ['VISA', 'MASTERCARD', 'AMEX'],
                'allowedPaymentTypes' => ['CARD'],
            ], [
                'Accept' => 'application/jwt',
            ]);
            [$header, $data] = explode('.', $contextData);
            $contextHeader   = json_decode(base64_decode($header));
            $public_key      = $this->doGetRequest('/flex/v2/public-keys/' . $contextHeader->kid);
            $captureContext  = \Firebase\JWT\JWT::decode(
                $contextData,
                \Firebase\JWT\JWK::parseKeySet([
                    'keys' => [$public_key],
                ], $contextHeader->alg)
            );
            $model['captureContext'] = $captureContext->ctx[0]->data;
            $model['public_key']     = (array) $captureContext->flx->jwk;
            $model['alg']            = $contextHeader->alg;
            $model['contextData']    = $contextData;

            $obtainNonce = new ObtainNonce($request->getModel());
            $obtainNonce->setModel($model);

            $this->gateway->execute($obtainNonce);
        }

        if (!$model->offsetExists('status')) {
            $decodedInfo = \Firebase\JWT\JWT::decode(
                trim($model['nonce'], '"'),
                \Firebase\JWT\JWK::parseKeySet([
                    'keys' => [$model['public_key']],
                ], $model['alg'])
            );
            $model['result'] = $decodedInfo;

            $model['status']               = 'success';
            $model['transactionReference'] = 'test';

            $client = new \Square\SquareClient(
                token: $this->config['access_token'],
                options: [
                    'baseUrl' => $this->config['sandbox'] ? \Square\Environments::Sandbox->value : \Square\Environments::Production->value,
                ]
            );

            $amount_money = new \Square\Types\Money();
            $amount_money->setAmount(round($model['amount'] * 100));
            $amount_money->setCurrency($model['currency']);

            $body = new \Square\Payments\Requests\CreatePaymentRequest([
                'sourceId'       => $model['nonce'],
                'idempotencyKey' => $request->getToken()->getHash(),
            ]);
            $body->setAmountMoney($amount_money);

            $item_name      = $model['square_item_name']  ?? false;
            $line_items     = $model['square_line_items'] ?? [];
            $order_discount = $model['square_discount']   ?? 0;

            if ($item_name) {
                $line_items[] = [
                    'name'   => $item_name,
                    'qty'    => 1,
                    'amount' => $model['amount'],
                ];
            }

            if ($line_items) {
                // Add Order
                $order = new \Square\Types\Order([
                    'locationId' => $model['location_id'],
                ]);
                $order_line_items = [];
                foreach ($line_items as $line_item) {
                    $order_line_item = new \Square\Types\OrderLineItem([
                        'quantity' => $line_item['qty'],
                    ]);
                    $order_line_item->setCatalogObjectId($this->getSquareCatalogueObject($client, $line_item['name']));

                    $line_amount_money = new \Square\Types\Money();
                    $line_amount_money->setAmount(round($line_item['amount'] * 100));
                    $line_amount_money->setCurrency($model['currency']);
                    $order_line_item->setBasePriceMoney($line_amount_money);
                    if ($line_item['note'] ?? '') {
                        $order_line_item->setNote($line_item['note']);
                    }

                    $order_line_items[] = $order_line_item;
                }
                $order->setLineItems($order_line_items);

                $orderbody = new \Square\Types\CreateOrderRequest();
                $orderbody->setOrder($order);
                $orderbody->setIdempotencyKey(uniqid());

                try {
                    $order_api_response = $client->orders->create($orderbody);
                    $order_id           = $order_api_response->getOrder()->getId();
                } catch (\Square\Exceptions\SquareApiException $e) {
                    $model['status'] = 'failed';
                    $model['error']  = 'failed';
                    foreach ($e->getErrors() as $error) {
                        $model['error'] = $error->getDetail();
                    }
                }

                if ($order_id) {
                    $body->setOrderId($order_id);
                }
            }

            $body->setAutocomplete(true);
            $body->setVerificationToken($model['verificationToken']);
            $body->setCustomerId($model['customer_id'] ?? null);
            $body->setLocationId($model['location_id']);
            $body->setReferenceId($model['reference_id'] ?? null);
            $body->setNote($model['description']);

            try {
                $api_response                  = $client->payments->create($body);
                $resultPayment                 = $api_response->getPayment();
                $model['status']               = 'success';
                $model['transactionReference'] = $resultPayment->getId();
                $model['result']               = $resultPayment;
            } catch (\Square\Exceptions\SquareApiException $e) {
                $model['status'] = 'failed';
                $model['error']  = 'failed';
                foreach ($e->getErrors() as $error) {
                    $model['error'] = $error->getDetail();
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }

    /**
     * Get the site to use
     * @return string
     */
    public function basehost()
    {
        return $this->config['sandbox'] ? 'apitest.cybersource.com' : 'api.cybersource.com';
    }

    public function baseurl()
    {
        return 'https://' . $this->basehost();
    }

    public function hashMessageBody($data)
    {
        $data              = json_encode($data);
        $utf8EncodedString = mb_convert_encoding($data, 'UTF-8', mb_detect_encoding($data));
        $digestEncode      = hash('sha256', $utf8EncodedString, true);

        return 'SHA-256=' . base64_encode($digestEncode);
    }

    // Signature Creation function
    public function generateToken($url, $messageBody, $method)
    {
        $host = $this->basehost();
        $date = date('D, d M Y G:i:s ') . 'GMT';
        if ($method == 'get' || $method == 'delete') {
            // signature creation for GET/DELETE
            $signatureString = 'host: ' . $host
                . PHP_EOL . 'date: ' . $date
                . PHP_EOL . 'request-target: ' . $method . ' ' . $url
                . PHP_EOL . 'v-c-merchant-id: ' . $this->config['organisation_id'];
            $headerString = 'host date request-target v-c-merchant-id';
        } elseif ($method == 'post' || $method == 'put' || $method == 'patch') {
            // signature creation for POST/PUT
            // Get digest data
            $digest          = $this->hashMessageBody($messageBody);
            $signatureString = 'host: ' . $host
                . PHP_EOL . 'date: ' . $date
                . PHP_EOL . 'request-target: ' . $method . ' ' . $url
                . PHP_EOL . 'digest: ' . $digest
                . PHP_EOL . 'v-c-merchant-id: ' . $this->config['organisation_id'];
            $headerString = 'host date request-target digest v-c-merchant-id';
        }

        return $this->accessTokenHeader($signatureString, $headerString);
    }

    // Purpose: using for access and return the signature token
    protected function accessTokenHeader($signatureString, $headerString)
    {
        $signatureByteString = mb_convert_encoding($signatureString, 'UTF-8', mb_detect_encoding($signatureString));
        $decodeKey           = base64_decode($this->config['shared_secret']);
        $signature           = base64_encode(hash_hmac('sha256', $signatureByteString, $decodeKey, true));
        $signatureHeader     = [
            'keyid="' . $this->config['key'] . '"',
            'algorithm="HmacSHA256"',
            'headers="' . $headerString . '"',
            'signature="' . $signature . '"',
        ];

        return implode(', ', $signatureHeader);
    }

    /**
     * Perform POST request to Cybersource servers
     * @param string $url relative path
     * @param string $data json encoded data
     * @return array|string
     */
    public function doPostRequest($url, $data, $headers = [])
    {
        $curl = curl_init();

        $digest = $this->hashMessageBody($data);

        $token = $this->generateToken($url, $data, 'post');

        $headers['Host']            = $this->basehost();
        $headers['v-c-merchant-id'] = $this->config['organisation_id'];
        $headers['Signature']       = $token;
        $headers['Date']            = date('D, d M Y G:i:s ') . 'GMT';
        $headers['v-c-client-id']   = 'cognito-payum-cybersource';
        $headers['Digest']          = $digest;

        if (!isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json';
        }
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->baseurl() . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => array_map(function ($key, $data) {
                return $key . ': ' . $data;
            }, array_keys($headers), $headers),
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception($err);
        }
        if ($headers['Accept'] == 'application/json') {
            return json_decode($response, true);
        }

        return $response;
    }

    /**
     * Perform GET request to cybersource servers
     * @param string $url relative path
     * @return array
     */
    public function doGetRequest($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->baseurl() . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception($err);
        }

        return json_decode($response, true);
    }
}
