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
            $model['nonce'] = trim($getHttpRequest->request['flexresponse'], '"');
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
            try {
                $decodedInfo = \Firebase\JWT\JWT::decode(
                    $model['nonce'],
                    \Firebase\JWT\JWK::parseKeySet([
                        'keys' => [$model['public_key']],
                    ], $model['alg'])
                );
                $model['result'] = $decodedInfo;

                $data = $this->doPostRequest('/pts/v2/payments', [
                    'clientReferenceInformation' => [
                        'code' => $model['payment_reference'],
                    ],
                    'processingInformation' => [
                        'capture' => true,
                    ],
                    'orderInformation' => [
                        'amountDetails' => [
                            'totalAmount' => $model['amount'],
                            'currency'    => $model['currency'],
                        ],
                        'billTo' => [
                            'firstName'          => $model['billing']['first_name'],
                            'lastName'           => $model['billing']['last_name'],
                            'email'              => $model['billing']['email'],
                            'address1'           => $model['billing']['address']['line1'],
                            'address2'           => $model['billing']['address']['line2'] ?? '',
                            'locality'           => $model['billing']['address']['city'],
                            'administrativeArea' => $model['billing']['address']['state'],
                            'postalCode'         => $model['billing']['address']['postal_code'],
                            'country'            => $model['billing']['address']['country'],
                        ],
                    ],
                    'tokenInformation' => [
                        'jti' => $decodedInfo->jti,
                    ],
                ]);
                $model['result'] = $data;
                if (isset($data['response'])) {
                    throw new \Exception($data['response']['msg']);
                }

                if ($data['status'] != 'AUTHORIZED') {
                    throw new \Exception('Error: status is ' . $data['status']);
                }

                $model['status']               = 'success';
                $model['transactionReference'] = $data['id'];
            } catch (\Exception $e) {
                $model['status'] = 'failed';
                $model['error']  = $e->getMessage();
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

        if (!isset($headers['Accept'])) {
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
