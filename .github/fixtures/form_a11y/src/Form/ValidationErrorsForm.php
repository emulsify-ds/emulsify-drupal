<?php

declare(strict_types=1);

namespace Drupal\form_a11y\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;

/**
 * Exercises all five error wrappers through a real Form API submission.
 */
final class ValidationErrorsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'emulsify_fixture_validation_errors';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#id' => 'fixture-name',
      '#description' => $this->t('Enter a display name.'),
    ];
    foreach (['details', 'fieldset'] as $type) {
      $form[$type] = [
        '#type' => $type,
        '#title' => $this->t('@type group', ['@type' => ucfirst($type)]),
        '#id' => 'fixture-' . $type,
        '#description' => $this->t('Complete the grouped field.'),
        '#tree' => TRUE,
        '#open' => TRUE,
        'value' => [
          '#type' => 'textfield',
          '#title' => $this->t('@type value', ['@type' => ucfirst($type)]),
          '#id' => 'fixture-' . $type . '-value',
          '#description' => $this->t('This field belongs to the group.'),
          // The group supplies this shared error message.
          '#error_no_message' => TRUE,
        ],
      ];
    }
    $form['date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Appointment'),
      '#id' => 'fixture-date',
      '#description' => $this->t('Enter an appointment date and time.'),
    ];
    $form['storage'] = [
      '#type' => 'textfield',
      '#theme_wrappers' => ['form_element__new_storage_type'],
      '#variant' => 'field-option',
      '#title' => $this->t('Storage option'),
      '#id' => 'fixture-storage',
      '#description' => $this->t('Enter a storage option.'),
    ];
    foreach (['radios', 'checkboxes'] as $type) {
      $form[$type] = [
        '#type' => $type,
        '#title' => $this->t('@type selection', ['@type' => ucfirst($type)]),
        '#id' => 'fixture-' . $type,
        '#description' => $this->t('Choose from the available options.'),
        '#options' => [
          'first' => $this->t('First option'),
          'second' => $this->t('Second option'),
        ],
      ];
    }
    $form['option_attributes'] = [
      '#type' => 'select',
      '#title' => $this->t('Delivery options'),
      '#id' => 'fixture-option-attributes',
      '#description' => $this->t('Choose from the available delivery and collection options.'),
      '#multiple' => TRUE,
      '#size' => 6,
      '#default_value' => ['flat_array', 'group_object'],
      '#options' => [
        'flat_array' => $this->t('Standard delivery'),
        'flat_object' => $this->t('Unavailable express delivery'),
        (string) $this->t('Collection options') => [
          'group_array' => $this->t('Unavailable collection'),
          'group_object' => $this->t('Local collection'),
        ],
      ],
      '#options_attributes' => [
        'flat_array' => [
          'data-option-source' => 'flat-array',
          'value' => 'spoof-flat-array',
          'selected' => FALSE,
        ],
        'flat_object' => new Attribute([
          'data-option-source' => 'flat-object',
          'disabled' => TRUE,
          'value' => 'spoof-flat-object',
          'selected' => TRUE,
        ]),
        'group_array' => [
          'data-option-source' => 'group-array',
          'disabled' => TRUE,
          'value' => 'spoof-group-array',
          'selected' => TRUE,
        ],
        'group_object' => new Attribute([
          'data-option-source' => 'group-object',
          'value' => 'spoof-group-object',
          'selected' => FALSE,
        ]),
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate fixture'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['name', 'details', 'fieldset', 'date', 'storage', 'radios', 'checkboxes'] as $name) {
      $form_state->setError($form[$name], $this->t('Fixture validation error for @element.', ['@element' => $name]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
