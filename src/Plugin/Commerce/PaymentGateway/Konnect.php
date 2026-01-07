<?php

namespace Drupal\commerce_konnect\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Konnect payment gateway.
 *
 * @CommercePaymentGateway(
 * id = "konnect",
 * label = "Konnect Tunisia",
 * display_label = "Konnect (Carte Bancaire / E-Dinar / Flouci)",
 * forms = {
 * "offsite-payment" = "Drupal\commerce_konnect\PluginForm\KonnectPaymentForm",
 * },
 * payment_method_types = {"credit_card"},
 * )
 */
class Konnect extends OffsitePaymentGatewayBase implements SupportsNotificationsInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'wallet_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['wallet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Receiver Wallet ID'),
      '#default_value' => $this->configuration['wallet_id'],
      '#required' => TRUE,
    ];

    // Display the Webhook URL for the user to copy into Konnect Dashboard
    if (!$this->parentEntity->isNew()) {
      $form['webhook_url'] = [
        '#type' => 'item',
        '#title' => $this->t('Webhook URL'),
        '#markup' => '<code>' . $this->getNotifyUrl()->setAbsolute()->toString() . '</code>',
        '#description' => $this->t('Copy this URL to your Konnect Dashboard under "Webhook URL".'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['api_key'] = $values['api_key'];
    $this->configuration['wallet_id'] = $values['wallet_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $payment_ref = $request->query->get('payment_ref');
    
    if (!$payment_ref) {
      return new Response('Missing payment_ref', 400);
    }

    // Verify payment status via Konnect API
    $endpoint = ($this->getMode() == 'test') 
      ? "https://api.preprod.konnect.network/api/v2/payments/$payment_ref"
      : "https://api.konnect.network/api/v2/payments/$payment_ref";

    try {
      $response = \Drupal::httpClient()->get($endpoint, [
        'headers' => ['x-api-key' => $this->configuration['api_key']],
      ]);
      $data = json_decode($response->getBody()->getContents(), TRUE);
      $konnect_payment = $data['payment'] ?? [];

      if ($konnect_payment['status'] === 'completed') {
        $order_id = $konnect_payment['orderId'];
        $order = \Drupal\commerce_order\Entity\Order::load($order_id);

        if ($order) {
          $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
          $payment = $payment_storage->create([
            'state' => 'completed',
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $this->parentEntity->id(),
            'order_id' => $order_id,
            'remote_id' => $payment_ref,
            'remote_state' => $konnect_payment['status'],
          ]);
          $payment->save();
          return new Response('OK');
        }
      }
    } catch (\Exception $e) {
      return new Response('Error: ' . $e->getMessage(), 500);
    }
  }
}