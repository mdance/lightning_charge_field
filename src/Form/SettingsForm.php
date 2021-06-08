<?php

namespace Drupal\lightning_charge_field\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\lightning_charge_field\LightningChargeFieldConstants;
use Drupal\lightning_charge_field\LightningChargeFieldServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the SettingsForm class.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Provides the module service.
   *
   * @var \Drupal\lightning_charge_field\LightningChargeFieldServiceInterface
   */
  protected $service;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LightningChargeFieldServiceInterface $service
  ) {
    parent::__construct($config_factory);

    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('lightning_charge_field')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lightning_charge_field_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      LightningChargeFieldConstants::KEY_SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $form['#tree'] = TRUE;

    $rebuild = $form_state->isRebuilding();
    $user_input = $form_state->getUserInput();

    $form = parent::buildForm($form, $form_state);

    $wrapper_id = 'wrapper-settings-form';

    $form['#attributes']['id'] = $wrapper_id;

    $key = 'price_mode';

    $options = $this->service->getPriceModes();

    $default_value = $this->service->getDefaultPriceMode();

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

    $default_value = $this->service->getDefaultPrice();

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

    $default_value = $this->service->getHide();

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    unset($values['actions']);

    $config = $this->config(LightningChargeFieldConstants::KEY_SETTINGS);

    foreach ($values as $key => $value) {
      $config->set($key, $value);
    }

    $config->save();


    parent::submitForm($form, $form_state);
  }

}
