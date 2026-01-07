<?php

namespace Drupal\commerce_konnect\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides the Konnect payment form.
 */
class KonnectPaymentForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $payment->getOrder();
    /** @var \Drupal\commerce_konnect\Plugin\Commerce\PaymentGateway\Konnect $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $config = $payment_gateway_plugin->getConfiguration();

    // Get billing profile to extract customer name.
    $billing_profile = $order->getBillingProfile();
    $address = $billing_profile ? $billing_profile->get('address')->first() : NULL;
	// Extracting customer name with fallbacks
	$first_name = $address ? $address->getGivenName() : $order->getCustomer()->getDisplayName();
	$last_name = $address ? $address->getFamilyName() : '';

    // Prepare the data for the Konnect API to create a payment.
    $data = [
      'receiverWalletId' => $config['receiver_wallet_id'],
      'amount' => $payment->getAmount()->getMinorUnits(),
      'currency' => $payment->getAmount()->getCurrencyCode(),
      'description' => $this->t('Payment for Order #@order_id', ['@order_id' => $order->id()]),
      'acceptedPaymentMethods' => [
        'balance',
        'bank_card',
        'wallet',
      ],
      'sendEmail' => !empty($config['send_email']),
      'email' => $order->getEmail(),
      'firstName' => $first_name ?: 'Customer',
      'lastName' => $last_name ?: $order->id(),
      'orderId' => $order->id(),
      'successUrl' => $form['#return_url'],
      'failUrl' => $form['#cancel_url'],
    ];

    // Add webhook URL if configured.
    if (!empty($config['webhook_url'])) {
      $data['webhookUrl'] = $config['webhook_url'];
    }

    try {
      // Call the public apiCall method from the gateway plugin.
      $response = $payment_gateway_plugin->apiCall('POST', '/payments', $data);
    }
    catch (\Exception $e) {
      // Log the detailed error for administrators.
      \Drupal::logger('commerce_konnect')->error('Konnect payment initiation failed for order @order_id: @message', [
        '@order_id' => $order->id(),
        '@message' => $e->getMessage(),
      ]);
      throw new PaymentGatewayException('Could not initialize the payment with Konnect. Please try again or contact support.');
    }

    if (empty($response['payment_url'])) {
      \Drupal::logger('commerce_konnect')->error('Konnect API response for order @order_id did not contain a payment_url. Response: @response', [
        '@order_id' => $order->id(),
        '@response' => print_r($response, TRUE),
      ]);
      throw new PaymentGatewayException('Could not retrieve payment URL from Konnect.');
    }

    // Redirect the user to the Konnect payment page.
    $redirect_url = $response['payment_url'];
    return $this->buildRedirectForm($form, $form_state, $redirect_url, []);
  }

}