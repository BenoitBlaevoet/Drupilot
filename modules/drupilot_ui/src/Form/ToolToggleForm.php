<?php

declare(strict_types=1);

namespace Drupal\drupilot_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupilot\PluginManager\McpToolPluginManager;
use Drupal\drupilot\Service\ToolRegistryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets administrators enable or disable each discovered MCP tool.
 */
final class ToolToggleForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    private readonly McpToolPluginManager $pluginManager,
    private readonly ToolRegistryService $toolRegistry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $container->get('plugin.manager.mcp_tool');
    /** @var \Drupal\drupilot\Service\ToolRegistryService $registry */
    $registry = $container->get('drupilot.tool_registry');
    return new static($manager, $registry);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupilot_ui_tool_toggle';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The augmented form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $definitions = $this->pluginManager->getToolDefinitions();

    if ($definitions === []) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('No MCP tools discovered.') . '</p>',
      ];
      return $form;
    }

    $grouped = [];
    foreach ($definitions as $definition) {
      $grouped[$definition->category][$definition->id] = $definition;
    }
    ksort($grouped);

    foreach ($grouped as $category => $tools) {
      $key = 'category_' . $category;
      $form[$key] = [
        '#type' => 'details',
        '#title' => $this->t('Category: @cat', ['@cat' => $category]),
        '#open' => TRUE,
        '#tree' => FALSE,
      ];

      ksort($tools);
      foreach ($tools as $id => $definition) {
        $form[$key]['tool_' . $id] = [
          '#type' => 'checkbox',
          '#title' => $definition->label !== '' ? $definition->label : $id,
          '#description' => $definition->description,
          '#default_value' => $this->toolRegistry->isEnabled($id) ? 1 : 0,
          '#parents' => ['tools', $id],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save tools'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('tools');
    if (!is_array($values)) {
      $values = [];
    }

    foreach ($this->pluginManager->getToolDefinitions() as $id => $_definition) {
      $checked = !empty($values[$id]);
      if ($checked) {
        $this->toolRegistry->enableTool($id);
        continue;
      }
      $this->toolRegistry->disableTool($id);
    }

    $this->messenger()->addStatus($this->t('MCP tool toggles saved.'));
  }

}
