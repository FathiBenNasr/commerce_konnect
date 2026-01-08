<?php

namespace Drupal\commerce_konnect\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Exception\RequestException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;

/**
 * Provides the Konnect payment gateway.
 *
 * @CommercePaymentGateway(
 * id = "konnect",
 * label = @Translation("Konnect"),
 * display_label = @Translation("Konnect"),
 * forms = {
 * "offsite-payment" = "Drupal\commerce_konnect\PluginForm\KonnectPaymentForm",
 * },
 * payment_method_types = {"credit_card"},
 * credit_card_types = {
 * "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 * },
 * )
 */
class Konnect extends OffsitePaymentGatewayBase {

  protected $httpClient;
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    $instance->logger = $container->get('logger.factory')->get('commerce_konnect');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'api_url' => 'https://api.konnect.network/api/v2',
      'receiver_wallet_id' => '',
      'send_email' => TRUE,
      'webhook_url' => '',
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
      '#description' => $this->t('Your Konnect API Key from the dashboard.'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['api_url'] = [
      '#type' => 'select',
      '#title' => $this->t('API Environment'),
      '#options' => [
        'https://api.konnect.network/api/v2' => $this->t('Live'),
        'https://api.sandbox.konnect.network/api/v2' => $this->t('Sandbox'),
      ],
      '#default_value' => $this->configuration['api_url'],
    ];

    $form['receiver_wallet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Receiver Wallet ID'),
      '#default_value' => $this->configuration['receiver_wallet_id'],
      '#required' => TRUE,
    ];

    $form['webhook_url'] = [
	'#type' => 'textfield',
	'#title' => $this->t('Webhook URL'),
	'#description' => $this->t('Copy your full site URL followed by /payment/notify/konnect (e.g., https://example.com/payment/notify/konnect)'),
	'#default_value' => $this->configuration['webhook_url'],
	'#required' => FALSE, // Laissez l'utilisateur le remplir manuellement pour plus de sécurité
	];
	
	$form['webhook_info'] = [
	'#type' => 'item',
	'#title' => $this->t('Webhook Configuration'),
	'#markup' => $this->t('Please configure your Konnect dashboard to point to: <br><code>/payment/notify/konnect</code>'),
	'#description' => $this->t('Ensure your site is accessible via a public URL for this to work.'),
	];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['api_key'] = $values['api_key'];
      $this->configuration['api_url'] = $values['api_url'];
      $this->configuration['receiver_wallet_id'] = $values['receiver_wallet_id'];
      $this->configuration['webhook_url'] = $values['webhook_url'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $payment_id = $request->query->get('payment_id');
    if (empty($payment_id)) {
      $this->logger->error('Konnect return URL accessed without a payment_id for order @order_id.', ['@order_id' => $order->id()]);
      throw new PaymentGatewayException('Payment confirmation failed: Payment ID not provided.');
    }

    try {
      $response = $this->apiCall('GET', '/payments/' . $payment_id);
    }
    catch (RequestException $e) {
      $this->logger->error('Konnect API request failed for order @order_id: @message', [
        '@order_id' => $order->id(),
        '@message' => $e->getMessage(),
      ]);
      throw new PaymentGatewayException('Payment confirmation failed: Could not connect to the payment provider.');
    }

    if (empty($response['orderId']) || (int) $response['orderId'] !== (int) $order->id()) {
      $this->logger->warning('Potential ID switching attack detected for order @order_id.', [
        '@order_id' => $order->id(),
        '@payment_id' => $payment_id,
      ]);
      throw new PaymentGatewayException('Payment confirmation failed: Payment ID does not match this order.');
    }

    if ($response['status'] === 'CAPTURED') {
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      $existing_payments = $payment_storage->loadByProperties(['remote_id' => $response['id']]);

      if (empty($existing_payments)) {
        $payment = $payment_storage->create([
          'state' => 'completed',
          'amount' => $order->getTotalPrice(),
          'payment_gateway' => $this->entityId,
          'order_id' => $order->id(),
          'remote_id' => $response['id'],
          'remote_state' => $response['status'],
        ]);
        $payment->save();
      }

      $this->messenger()->addStatus($this->t('Your payment was successful with Transaction ID: @transaction_id', ['@transaction_id' => $response['transaction_id'] ?? $response['id']]));
    }
    else {
      throw new PaymentGatewayException('Payment failed at the payment provider.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $payment_ref = $request->query->get('payment_ref');

    if (!$payment_ref) {
      return new Response('No payment reference provided', 400);
    }

    try {
      $payment_details = $this->apiCall('GET', "/payments/{$payment_ref}");
      if (empty($payment_details['payment'])) {
        throw new \Exception('Invalid payment details.');
      }
      
      $konnect_payment = $payment_details['payment'];
      $order_id = $konnect_payment['orderId'];
      $remote_status = $konnect_payment['status'];

      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $order_storage->load($order_id);

      if (!$order) {
        throw new PaymentGatewayException("Order $order_id not found.");
      }

      if ($remote_status === 'completed') {
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payments = $payment_storage->loadByProperties(['remote_id' => $payment_ref]);
        $payment = reset($payments);

        if (!$payment) {
          $payment = $payment_storage->create([
            'state' => 'completed',
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $this->entityId,
            'order_id' => $order->id(),
            'remote_id' => $payment_ref,
            'remote_state' => $remote_status,
          ]);
        }
        else {
          $payment->setState('completed');
        }

        $payment->save();
        $this->logger->info('Order @id marked as paid via Webhook.', ['@id' => $order_id]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Webhook error: @message', ['@message' => $e->getMessage()]);
      return new Response('Error processing webhook', 500);
    }

    return new Response('OK');
  }

  /**
   * Performs an API call to Konnect using x-api-key authentication.
   */
  public function apiCall($method, $endpoint, $data = []) {
    $url = $this->configuration['api_url'] . $endpoint;
    $options = [
      'headers' => [
        'x-api-key' => $this->configuration['api_key'],
        'Content-Type' => 'application/json',
      ],
    ];
    if (!empty($data)) {
      $options['json'] = $data;
    }

    $response = $this->httpClient->request($method, $url, $options);
    return json_decode($response->getBody()->getContents(), TRUE);
  }
}