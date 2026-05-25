<?php

declare(strict_types=1);

namespace Drupal\drupilot\FieldType;

/**
 * Contract for services that contribute field types to the MCP field_create tool.
 *
 * Tag your service with `drupilot.field_type_provider` to have its types
 * automatically included in the tool's supported-types list.
 */
interface FieldTypeProviderInterface {

  /**
   * Returns the Drupal field type plugin IDs this provider supports.
   *
   * @return string[]
   *   Machine names of supported Drupal field types (e.g. 'string', 'link').
   */
  public function getSupportedTypes(): array;

}
