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

class ObtainNonceAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var string
     */
    protected $templateName;
    protected $use_sandbox;

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName, bool $use_sandbox)
    {
        $this->templateName = $templateName;
        $this->use_sandbox  = $use_sandbox;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        ray(__FILE__ . __FUNCTION__);

        /** @var $request ObtainNonce */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if ($model['card']) {
            throw new LogicException('The token has already been set.');
        }

        $getHttpRequest = new GetHttpRequest();
        ray($getHttpRequest);
        $this->gateway->execute($getHttpRequest);

        // Received payment information from Square
        if (isset($getHttpRequest->request['payment_intent'])) {
            $model['nonce']             = $getHttpRequest->request['payment_intent'];
            $model['verificationToken'] = $getHttpRequest->request['verification_token'];

            return;
        }

        $billingContact = [
            'email' => $model['email'] ?? '',
        ];
        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, [
            'merchant_reference' => $model['merchant_reference'] ?? '',
            'amount'             => $model['currencySymbol'] . ' ' . number_format($model['amount'], $model['currencyDigits']),
            'numeric_amount'     => $model['amount'],
            'currencyCode'       => $model['currency'],
            'actionUrl'          => $getHttpRequest->uri,
            'captureContext'     => $model['captureContext'],
            'contextData'        => $model['contextData'],
            'public_key'         => $model['public_key'],
            'imgUrl'             => $model['img_url'],
        ]));

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
