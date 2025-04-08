<?php

namespace Drupal\civicrm_entity_vbo_example\Plugin\Action;

use Drupal\civicrm_entity\CiviCrmApi;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to tag a CiviCRM Contact.
 *
 * @Action(
 *   id = "civicrm_contact_add_tag",
 *   label = @Translation("Tag Contact"),
 *   type = "civicrm_contact",
 *   confirm = TRUE,
 * )
 */
#[Action(
  id: 'civicrm_contact_add_tag',
  label: new TranslatableMarkup('Tag Contact'),
  type: 'civicrm_contact',
)]
class CivicrmContactAddTag extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * The CiviCRM API service.
   *
   * @var \Drupal\civicrm_entity\CiviCrmApi
   */
  protected $civicrmApi;

  /**
   * CivicrmContactAddTag constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\civicrm_entity\CiviCrmApi $civicrm_entity_api
   *   The CiviCRM API service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CiviCrmApi $civicrm_entity_api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->civicrmApi = $civicrm_entity_api;
  }

  /**
   * Create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('civicrm_entity.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!empty($this->configuration['selected_tag']) && !empty($entity)) {
      try {
        $this->civicrmApi->save('EntityTag', [
          'tag_id'   => $this->configuration['selected_tag'],
          'contact_id' => $entity->id(),
          "entity_table" => "civicrm_contact",
        ]);
        $this->messenger()->addMessage('Tagged: ' . $entity->label() . ' with tag: ' . $this->fetchTagLabel($this->configuration['selected_tag']));
      }
      catch (\Exception $e) {

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state): array {
    $tags = $this->fetchTags();

    $form['allowed_tags'] = [
      '#title' => $this->t('Allowed Tags'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $tags,
      '#default_value' => $values['allowed_tags'] ?? [],
    ];
    return $form;
  }

  /**
   * Configuration form builder.
   *
   * If this method has implementation, the action is
   * considered to be configurable.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $tags = [];
    if (!empty($this->context['preconfiguration']['allowed_tags'])) {
      $tags = $this->fetchTags($this->context['preconfiguration']['allowed_tags']);
    }
    $form['selected_tag'] = [
      '#title' => t('Tag'),
      '#type' => 'select',
      '#options' => $tags,
      '#default_value' => $form_state->getValue('selected_tag'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

  /**
   * Fetch array of tag titles, keyed by id.
   *
   * @param array $ids
   *   Array of ids.
   *
   * @return array
   *   The array of group titles.
   */
  private function fetchTags(array $ids = []) {
    $tags = [];
    try {
      $params = [
        'sequential' => FALSE,
        'return'     => ["label"],
        'options'    => ['limit' => 0],
      ];
      if (!empty($ids)) {
        $params['id'] = ['IN' => $ids];
      }
      $api_tags = $this->civicrmApi->get('tag', $params);
      if (!empty($api_tags)) {
        foreach ($api_tags as $tid => $tag) {
          $tags[$tid] = $tag['label'];
        }
      }
    }
    catch (\Exception $e) {

    }
    return $tags;
  }

  /**
   * Return tag label given tag id.
   *
   * @param int $tag_id
   *   The tag id.
   *
   * @return string
   *   The label.
   */
  private function fetchTagLabel($tag_id) {
    try {
      if (!empty($tag_id) && is_numeric($tag_id)) {
        $value = $this->civicrmApi->getSingle('Tag', [
          'return' => ["label"],
          'id'     => $tag_id,
        ]);
        if (!empty($value['label'])) {
          return $value['label'];
        }
      }
    }
    catch (\Exception $e) {

    }
    return '';
  }

}
