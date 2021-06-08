<?php

namespace Drupal\lightning_charge_field;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides the LightningChargeFieldServiceInterface interface.
 */
interface LightningChargeFieldServiceInterface {

  /**
   * Gets the default price mode.
   *
   * @return string
   *   A string containing the default price mode.
   */
  public function getDefaultPriceMode();

  /**
   * Gets the default price.
   *
   * @return array
   *   An array containing the default price.
   */
  public function getDefaultPrice();

  /**
   * Gets the hide on error setting.
   *
   * @return bool
   *   A boolean indicating whether to hide the field on errors.
   */
  public function getHide();

  /**
   * Gets the price modes.
   *
   * @return array
   *   An array of price modes.
   */
  public function getPriceModes();

  /**
   * Gets the invoices for an entity.
   *
   * @param EntityInterface $entity
   *   The entity object.
   * @param string $view_mode
   *   A string containing the view mode.
   * @param string $field_name
   *   A string containing the field name.
   * @param array $price
   *   An array containing the price.
   * @param string $hash
   *   A variable passed by reference to receive the hash.
   * @param mixed $account
   *   The account object.
   *
   * @return \Drupal\lightning_charge\Invoice[]
   *   An array of invoices.
   */
  public function getInvoices(EntityInterface $entity, $view_mode, $field_name, $price, &$hash, $account = NULL);

  /**
   * Creates an invoice.
   *
   * @param array $props
   *   An array of invoice properties.
   *
   * @return \Drupal\lightning_charge\InvoiceInterface
   *   The invoice object.
   */
  public function createInvoice($props = []);

}
