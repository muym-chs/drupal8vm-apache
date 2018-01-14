<?php

namespace Drupal\multiversion\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Returns an empty value for the workspace entity reference field.
 */
class EntityReferenceItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return ($data instanceof $this->supportedInterfaceOrClass) && $data->getFieldDefinition()->getName() === 'workspace';
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    return [];
  }

}
