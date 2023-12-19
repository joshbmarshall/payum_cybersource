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

class ObtainNonceAction implements ActionInterface, GatewayAwareInterface {
    use GatewayAwareTrait;


    /**
     * @var string
     */
    protected $templateName;

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName) {
        $this->templateName = $templateName;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request) {
        /** @var $request ObtainNonce */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if ($model['card']) {
            throw new LogicException('The token has already been set.');
        }
        $uri = \League\Uri\Http::createFromServer($_SERVER);

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        // Received payment intent information from Stripe
        if (isset($getHttpRequest->request['payment_intent'])) {
            $model['nonce'] = $getHttpRequest->request['payment_intent'];
            return;
        }
        // Comma separate list of enabled payment types for this transaction - get list of payment types at https://stripe.com/docs/api/payment_methods/object#payment_method_object-type
        $limit_payment_type = $model['limit_payment_type'] ?? '';
        $paymentIntentData = [
            'amount' => round($model['amount'] * pow(10, $model['currencyDigits'])),
            'shipping' => $model['shipping'],
            'currency' => $model['currency'],
            'metadata' => ['integration_check' => 'accept_a_payment'],
            'statement_descriptor' => $model['statement_descriptor_suffix'],
            'description' => $model['description'],
        ];
        if ($limit_payment_type) {
            $paymentIntentData['payment_method_types'] = explode(',', $limit_payment_type);
        } else {
            $paymentIntentData['automatic_payment_methods'] = [
                'enabled' => true,
                'allow_redirects' => 'always',
            ];
        }

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
    public function supports($request) {
        return
            $request instanceof ObtainNonce &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
