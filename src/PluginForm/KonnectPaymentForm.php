<?php

namespace Drupal\commerce_konnect\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

class KonnectPaymentForm extends BasePaymentOffsiteForm {

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $config = $gateway_plugin->getConfiguration();

    $url = ($gateway_plugin->getMode() == 'test')
      ? 'https://api.preprod.konnect.network/api/v2/payments/init-payment'
      : 'https://api.konnect.network/api/v2/payments/init-payment';

    // Amount in Millimes
    $amount = (int) ($payment->getAmount()->getNumber() * 1000);

    try {
      $response = \Drupal::httpClient()->post($url, [
        'headers' => [
          'x-api-key' => $config['api_key'],
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'receiverWalletId' => $config['wallet_id'],
          'amount' => $amount,
          'token' => 'TND',
          'orderId' => $payment->getOrderId(),
          'successUrl' => $form['#return_url'],
          'failUrl' => $form['#cancel_url'],
          'silentWebhook' => TRUE,
        ],
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $this->buildRedirectForm($form, $form_state, $data['payUrl'], [], 'get');
    } catch (\Exception $e) {
      \Drupal::messenger()->addError('Payment initiation failed.');
      return $form;
    }
  }
}