<?php

namespace Drupal\lightning_charge_field\Plugin\Field\FieldFormatter;

use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\lightning_charge\LightningChargeConstants;
use Drupal\lightning_charge_field\LightningChargeFieldConstants;
use Drupal\lightning_charge_field\LightningChargeFieldServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the PaymentFieldFormatter class.
 *
 * @FieldFormatter(
 *   id = "lightning_charge_payment",
 *   module = "lightning_charge_field",
 *   label = @Translation("Lightning Charge Payment"),
 *   field_types = {}
 * )
 */
class PaymentFieldFormatter extends FormatterBase {

  /**
   * Provides the module service.
   *
   * @var \Drupal\lightning_charge_field\LightningChargeFieldServiceInterface
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('lightning_charge_field')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LightningChargeFieldServiceInterface $service
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $output = parent::defaultSettings();

    /** @var LightningChargeFieldServiceInterface $service */
    $service = \Drupal::service('lightning_charge_field');

    $output['price_mode'] = LightningChargeFieldConstants::PRICE_MODE_CUSTOM;
    $output['price'] = $service->getDefaultPrice();
    $output['hide'] = $service->getHide();

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $key = 'price_mode';

    $options = $this->service->getPriceModes();

    $default_value = $this->getSetting($key);

    $selector = $key;

    $form[$key] = [
      '#type' => 'radios',
      '#title' => $this->t('Price Mode'),
      '#options' => $options,
      '#default_value' => $default_value,
      '#attributes' => [
        'class' => [
          $selector,
        ],
      ],
    ];

    $key = 'price';

    $default_value = $this->getSetting($key);

    $form[$key] = [
      '#type' => 'commerce_price',
      '#title' => t('Price'),
      '#default_value' => $default_value,
      '#states' => [
        'visible' => [
          ".$selector" => [
            'value' => LightningChargeFieldConstants::PRICE_MODE_CUSTOM,
          ],
        ],
      ],
    ];

    $key = 'hide';

    $default_value = $this->getSetting($key);

    $form[$key] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide Field On Error'),
      '#default_value' => $default_value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $output = parent::settingsSummary();

    $keys = [
      'price_mode' => 'Price Mode: @value',
      'price' => 'Price: @value',
      'hide' => 'Hide On Error: @value',
    ];

    foreach ($keys as $key => $value) {
      $result = $this->getSetting($key);

      if ($result) {
        switch ($key) {
          case 'price_mode':
            $options = $this->service->getPriceModes();

            $result = $options[$result];

            break;
          case 'price':
            $result = Price::fromArray($result);

            break;
          case 'hide':
            $result = $result ? $this->t('Yes') : $this->t('No');

            break;
        }

        $args = [];

        $args['@value'] = $result;

        $output[$key] = $this->t($value, $args);
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $output = [];

    $entity = $items->getEntity();

    $view_mode = $this->viewMode;

    $field_name = $this->fieldDefinition->getName();

    $settings = $this->getSettings();

    $price_mode = $settings['price_mode'];
    $price = $settings['price'];

    if ($price_mode == LightningChargeFieldConstants::PRICE_MODE_DONATION) {
      $price = [
        'number' => '0.00',
        'currency_code' => 'USD',
      ];
    }

    $paid = FALSE;

    try {
      $invoices = $this->service->getInvoices($entity, $view_mode, $field_name, $price, $hash);

      if ($invoices) {
        foreach ($invoices as $id => $invoice) {
          $status = $invoice->getStatus();

          if ($status == LightningChargeConstants::STATUS_EXPIRED) {
            continue;
          }
          else {
            if ($status == LightningChargeConstants::STATUS_PAID) {
              $paid = TRUE;

              break;
            }
          }

          $wrapper_id = LightningChargeFieldConstants::PREFIX . $hash;

          $output = $invoice->toRenderable();

          $output['#type'] = 'container';
          $output['#attributes']['id'] = $wrapper_id;
          $output['#attributes']['class'] = [
            'lightning-charge-field-invoice',
          ];
        }
      }
    } catch (\Exception $e) {
      $hide = $this->getSetting('hide');

      if (!$hide) {
        $output = [
          '#markup' => t('The field is not available at this time, please try again later.'),
        ];
      }
    }

    if ($paid) {
      return FALSE;
    }

    $output = [
      '0' => $output,
    ];

    return $output;
  }

}
