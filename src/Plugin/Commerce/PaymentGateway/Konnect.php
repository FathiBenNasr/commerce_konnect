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

  // ... Gardez vos méthodes onReturn() et onNotify() telles quelles
}