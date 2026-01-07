<?php

namespace Drupal\commerce_konnect\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides the Konnect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "konnect",
 *   label = @Translation("Konnect"),
 *   display_label = @Translation("Konnect"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_konnect\PluginForm\KonnectPaymentForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class Konnect extends OffsitePaymentGatewayBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new Konnect object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $http_client, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('commerce_konnect');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'api_secret' => '',
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
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $this->configuration['api_secret'],
      '#required' => TRUE,
    ];

    $form['api_url'] = [
      '#type' => 'select',
      '#title' => $this->t('API Environment'),
      '#options' => [
        'https://api.konnect.network/api/v2' => $this->t('Live'),
        'https://api.preprod.konnect.network/api/v2' => $this->t('Sandbox'),
      ],
      '#default_value' => $this->configuration['api_url'],
      '#required' => TRUE,
    ];

    $form['receiver_wallet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Receiver Wallet ID'),
      '#description' => $this->t('The wallet ID to receive payments. Find this in your Konnect dashboard.'),
      '#default_value' => $this->configuration['receiver_wallet_id'],
      '#required' => TRUE,
    ];

    $form['send_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send payment confirmation email to customer via Konnect'),
      '#description' => $this->t('If checked, Konnect will handle sending the payment receipt.'),
      '#default_value' => $this->configuration['send_email'],
    ];

    $form['webhook_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook URL'),
      '#description' => $this->t('Optional: A URL to receive asynchronous payment status updates. This is more reliable than the return page. Leave empty to disable.'),
      '#default_value' => $this->configuration['webhook_url'],
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
      $this->configuration['api_secret'] = $values['api_secret'];
      $this->configuration['api_url'] = $values['api_url'];
      $this->configuration['receiver_wallet_id'] = $values['receiver_wallet_id'];
      $this->configuration['send_email'] = $values['send_email'];
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

  // *** CRITICAL SECURITY FIX: Prevent ID switching attacks ***
  // Verify that the payment returned from Konnect actually belongs to the current order.
  if (empty($response['orderId']) || (int) $response['orderId'] !== (int) $order->id()) {
    $this->logger->warning('Potential ID switching attack detected for order @order_id. Konnect payment ID @payment_id is associated with a different order ID (@konnect_order_id).', [
      '@order_id' => $order->id(),
      '@payment_id' => $payment_id,
      '@konnect_order_id' => $response['orderId'] ?? 'NULL',
    ]);
    throw new PaymentGatewayException('Payment confirmation failed: Payment ID does not match this order.');
  }

  if ($response['status'] === 'CAPTURED') {
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    // Prevent duplicate payments by checking for an existing payment with the same remote ID.
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
      $this->logger->notice('Konnect payment created for order @order_id with remote ID @remote_id.', [
        '@order_id' => $order->id(),
        '@remote_id' => $response['id'],
      ]);
    }
    else {
      $this->logger->info('Konnect return for order @order_id with remote ID @remote_id received, but payment already exists. No action taken.', [
        '@order_id' => $order->id(),
        '@remote_id' => $response['id'],
      ]);
    }

    $this->messenger()->addStatus($this->t('Your payment was successful with Transaction ID: @transaction_id', ['@transaction_id' => $response['transaction_id'] ?? $response['id']]));
  }
  else {
    $this->logger->warning('Konnect payment for order @order_id failed with status: @status', ['@order_id' => $order->id(), '@status' => $response['status']]);
    throw new PaymentGatewayException('Payment failed at the payment provider. Please try again or contact support.');
  }
}

  /**
   * Performs an API call to the Konnect gateway.
   *
   * Made public to be accessible from the OffsiteForm plugin.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $endpoint
   *   The API endpoint (e.g., '/payments').
   * @param array $data
   *   The request body data for POST requests.
   *
   * @return array
   *   The JSON response from the API, decoded into an array.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function apiCall($method, $endpoint, $data = []) {
    $config = $this->getConfiguration();
    $url = $config['api_url'] . $endpoint;

    $options = [];
    if (!empty($data)) {
      $options['json'] = $data;
    }

    // Use Basic Auth as per the API documentation.
    $options['auth'] = [$config['api_key'], $config['api_secret']];

    $this->logger->debug('Making Konnect API request: @method @url', ['@method' => $method, '@url' => $url]);

    $response = $this->httpClient->request($method, $url, $options);

    return json_decode($response->getBody()->getContents(), TRUE);
  }

}