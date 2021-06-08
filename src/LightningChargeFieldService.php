<?php

namespace Drupal\lightning_charge_field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lightning_charge\LightningChargeConstants;
use Drupal\lightning_charge\LightningChargeServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Provides the LightningChargeFieldService class.
 */
class LightningChargeFieldService implements LightningChargeFieldServiceInterface {

  use StringTranslationTrait;

  /**
   * Provides the request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Provides the request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Provides the session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Provides the database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Provides the current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Provides the config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Provides the configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Provides the lightning charge service.
   *
   * @var \Drupal\lightning_charge\LightningChargeServiceInterface
   */
  protected $lightningCharge;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    RequestStack $request_stack,
    SessionInterface $session,
    Connection $connection,
    AccountProxyInterface $current_user,
    ConfigFactoryInterface $config_factory,
    LightningChargeServiceInterface $lightning_charge
  ) {
    $this->requestStack = $request_stack;
    $this->request = $request_stack->getCurrentRequest();

    $this->session = $session;

    $this->connection = $connection;

    $this->currentUser = $current_user;

    $this->configFactory = $config_factory;
    $this->config = $config_factory->getEditable(LightningChargeFieldConstants::KEY_SETTINGS);

    $this->lightningCharge = $lightning_charge;
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultPriceMode() {
    $output = $this->config->get('price_mode');

    if (!$output) {
      $output = LightningChargeFieldConstants::PRICE_MODE_CUSTOM;
    }

    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultPrice() {
    $output = $this->config->get('price');

    if (!$output) {
      $output = [
        'number' => '0.00',
        'currency_code' => 'USD',
      ];
    }

    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function getHide() {
    $output = $this->config->get('hide');

    if (!$output) {
      $output = FALSE;
    }

    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function getPriceModes() {
    $output = [];

    $output[LightningChargeFieldConstants::PRICE_MODE_CUSTOM] = $this->t('Custom');
    $output[LightningChargeFieldConstants::PRICE_MODE_DONATION] = $this->t('Donation');

    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function getInvoices(EntityInterface $entity, $view_mode, $field_name, $price, &$hash, $account = NULL) {
    $output = [];

    if (is_null($account)) {
      $account = $this->currentUser;
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $id = $entity->id();
    $label = $entity->label();

    $args = [];

    $args['@label'] = $label;
    $args['@field_name'] = $entity->$field_name->getFieldDefinition()->getLabel();

    $description = t('@label @field_name', $args);

    $props = [
      'description' => $description,
      'amount' => $price['number'],
      'currency' => $price['currency_code'],
      'metadata' => [
        LightningChargeConstants::KEY_TYPE => LightningChargeFieldConstants::TYPE,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'entity' => $id,
        'view_mode' => $view_mode,
        'field_name' => $field_name,
      ],
    ];

    $metadata = &$props['metadata'];

    if ($account->isAnonymous()) {
      $metadata['ip'] = $this->request->getClientIp();
      $metadata['session'] = $this->session->getId();
    } else {
      $metadata['uid'] = $account->id();
    }

    $data = json_encode($metadata);
    $hash = hash('sha256', $data);

    $metadata['hash'] = $hash;

    $a = 'i';

    $query = $this->connection->select(LightningChargeFieldConstants::TABLE_INVOICES, $a);

    $fields = [
      'id',
      'invoice_id',
    ];

    $query->fields($a, $fields);

    $query->condition('hash', $hash);

    $results = $query->execute();

    $create = TRUE;

    if ($results) {
      foreach ($results as $result) {
        $id = $result->invoice_id;

        if ($id) {
          $invoice = $this->lightningCharge->invoice($id);

          if ($invoice) {
            $amount = $invoice->getAmount();
            $currency_code = $invoice->getCurrency();

            if ($amount == $price['number'] && $currency_code == $price['currency_code']) {
              $status = $invoice->getStatus();

              $output[$id] = $invoice;

              switch ($status) {
                case LightningChargeConstants::STATUS_UNPAID:
                case LightningChargeConstants::STATUS_PAID:
                  $create = FALSE;

                  break;
              }
            }
          }
        }
      }
    }

    if ($create) {
      $key = 'amount';

      if (empty($props[$key])) {
        unset($props[$key]);

        $key = 'currency';

        if (isset($props[$key])) {
          unset($props[$key]);
        }
      }

      $invoice = $this->createInvoice($props);
      $id = $invoice->getId();

      $query = $this->connection->insert(LightningChargeFieldConstants::TABLE_INVOICES);

      $fields = [];

      $fields['invoice_id'] = $id;
      $fields['hash'] = $hash;

      $query->fields($fields);

      $query->execute();

      $output[$id] = $invoice;
    }

    $this->session->set('lightning_charge_field_invoices', TRUE);

    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function createInvoice($props = []) {
    $output = $this->lightningCharge->createInvoice($props);

    return $output;
  }

}
