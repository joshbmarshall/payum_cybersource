<?php
namespace Cognito\PayumCybersource;

use Cognito\PayumCybersource\Action\ConvertPaymentAction;
use Cognito\PayumCybersource\Action\CaptureAction;
use Cognito\PayumCybersource\Action\ObtainNonceAction;
use Cognito\PayumCybersource\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class StripeElementsGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'payum.factory_name' => 'stripe_elements',
            'payum.factory_title' => 'stripe_elements',

            'payum.template.obtain_nonce' => "@PayumCybersource/Action/obtain_nonce.html.twig",

            'payum.action.capture' => function (ArrayObject $config) {
                return new CaptureAction($config);
            },
            'payum.action.status' => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.action.obtain_nonce' => function (ArrayObject $config) {
                return new ObtainNonceAction($config['payum.template.obtain_nonce']);
            },
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = array(
                'sandbox' => true,
            );
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = [];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api((array) $config, $config['payum.http_client'], $config['httplug.message_factory']);
            };
        }
        $payumPaths = $config['payum.paths'];
        $payumPaths['PayumCybersource'] = __DIR__ . '/Resources/views';
        $config['payum.paths'] = $payumPaths;
    }
}
