<?php

namespace Cognito\PayumCybersource\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
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

        $model['app_id']      = $this->config['app_id'];
        $model['location_id'] = $this->config['location_id'];
        $model['img_url']     = $this->config['img_url'] ?? '';

        $obtainNonce = new ObtainNonce($request->getModel());
        $obtainNonce->setModel($model);

        $this->gateway->execute($obtainNonce);
        if (!$model->offsetExists('status')) {
            $model['status']               = 'success';
            $model['transactionReference'] = 'test';
            $model['result']               = 'result';

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

                if (isset($model['customer'])) {
                    $order->setCustomerId($this->getSquareCustomer($client, $model['customer']));
                }

                if ($order_discount) {
                    $discount_amount_money = new \Square\Types\Money();
                    $discount_amount_money->setAmount(round($order_discount * 100));
                    $discount_amount_money->setCurrency($model['currency']);
                    $order_line_item_discount = new \Square\Types\OrderLineItemDiscount();
                    $order_line_item_discount->setUid('discount');
                    $order_line_item_discount->setName('Discount');
                    $order_line_item_discount->setAmountMoney($discount_amount_money);
                    $order_line_item_discount->setScope('ORDER');
                    $order->setDiscounts([$order_line_item_discount]);
                }

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
}
