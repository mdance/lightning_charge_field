<?php

namespace Drupal\lightning_charge_field;

/**
 * Provides the LightningChargeFieldConstants class.
 */
class LightningChargeFieldConstants {

  /**
   * Provides the main table.
   *
   * @var string
   */
  const TABLE = 'lightning_charge_field';

  /**
   * Provides the invoices table.
   *
   * @var string
   */
  const TABLE_INVOICES = 'lightning_charge_field_invoices';

  /**
   * Provides the configuration key.
   *
   * @var string
   */
  const KEY_SETTINGS = 'lightning_charge_field.settings';

  /**
   * Provides the metadata type.
   *
   * @var string
   */
  const TYPE = 'lightning_charge_field';

  /**
   * Provides the wrapper prefix.
   *
   * @var string
   */
  const PREFIX = 'lightning-charge-field-';

  /**
   * Provides the custom price mode.
   */
  const PRICE_MODE_CUSTOM = 'custom';

  /**
   * Provides the donation price mode.
   */
  const PRICE_MODE_DONATION = 'donation';

  /**
   * Provides the administration permission.
   */
  const PERMISSION_ADMIN = 'administer lightning_charge_field';

}
