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
        /** @var $request ObtainNonce */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if ($model['card']) {
            throw new LogicException('The token has already been set.');
        }

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);

        throw new HttpResponse($this->renderTemplate([
            'merchant_reference' => $model['merchant_reference'] ?? '',
            'amount'             => $model['currencySymbol'] . ' ' . number_format($model['amount'], $model['currencyDigits']),
            'numeric_amount'     => $model['amount'],
            'currencyCode'       => $model['currency'],
            'actionUrl'          => $getHttpRequest->uri,
            'captureContext'     => $model['captureContext'],
            'contextData'        => $model['contextData'],
            'imgUrl'             => $model['img_url'],
            'tailwindcss'        => file_get_contents(dirname(__DIR__) . '/Resources/views/Action/style.css'),
        ]));

        throw new HttpResponse($renderTemplate->getResult());
    }

    private function renderTemplate($vars)
    {
        ob_start();
        extract($vars);

        include dirname(__DIR__) . '/Resources/views/Action/obtain_nonce.php';

        return ob_get_clean();
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
