<?php

namespace Drupal\emulsify\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconPreviewBuilder;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\file\Entity\File;
use Psr\Log\LoggerInterface;

/**
 * Theme settings hook handlers.
 */
final class ThemeSettingsHooks {

  use StringTranslationTrait;

  /**
   * Generates favicon packages from uploaded theme assets.
   */
  private FaviconPackageGenerator $packageGenerator;

  /**
   * Builds admin previews for generated favicon assets.
   */
  private FaviconPreviewBuilder $previewBuilder;

  /**
   * Logger channel for theme-settings generation failures.
   */
  private LoggerInterface $logger;

  /**
   * Creates the theme settings hook handler.
   */
  public function __construct(
    private readonly ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly MessengerInterface $messenger,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    FileUrlGeneratorInterface $fileUrlGenerator,
    TimeInterface $time,
  ) {
    $this->packageGenerator = new FaviconPackageGenerator(
      $fileSystem,
      $fileUrlGenerator,
      $configFactory,
      $cacheTagsInvalidator,
      $time,
    );
    $this->previewBuilder = new FaviconPreviewBuilder($fileUrlGenerator);
    $this->logger = $loggerChannelFactory->get('emulsify');
  }

  /**
   * Handles hook_form_system_theme_settings_alter().
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $site_name = $this->getSiteName();
    $theme_name = FaviconSettings::resolveThemeName($form_state);
    $settings = FaviconSettings::loadThemeSettings($theme_name, $this->themeSettingsProvider, $site_name);
    $source_file = $this->resolveStoredSourceFile($settings);
    $package_status = $this->buildPackageStatus($theme_name, $settings, $source_file);
    $has_generated_package = $package_status['package_exists'];
    $has_preview_source = $package_status['source_available'] || $has_generated_package;
    $preview_settings = $settings;
    if ($package_status['path'] !== '') {
      $preview_settings['favicon_package_path'] = $package_status['path'];
    }

    // Remove the legacy page-element display toggles and persist the values
    // the base theme expects instead of exposing conflicting controls.
    $page_element_defaults = [
      'toggle_logo' => 0,
      'toggle_name' => 0,
      'toggle_slogan' => 0,
      'toggle_node_user_picture' => 0,
      'toggle_comment_user_picture' => 0,
      'toggle_comment_user_verification' => 0,
      'toggle_favicon' => 1,
    ];
    foreach ($page_element_defaults as $key => $value) {
      $form[$key] = [
        '#type' => 'value',
        '#value' => $value,
      ];
    }

    // Replace Drupal's legacy favicon controls with the generated workflow.
    unset($form['favicon'], $form['theme_settings']);
    $form['default_favicon'] = [
      '#type' => 'value',
      '#value' => 1,
    ];
    $form['favicon_path'] = [
      '#type' => 'value',
      '#value' => '',
    ];

    $form['#attached']['library'][] = 'emulsify/favicon_admin';
    $form['#validate'][] = [self::class, 'validateFaviconSettings'];
    $form['#submit'][] = [self::class, 'submitFaviconSettings'];

    $form['favicon_package_hash'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['favicon_package_hash'],
    ];
    $form['favicon_package_path'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['favicon_package_path'],
    ];
    $form['favicon_package_generated_at'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['favicon_package_generated_at'],
    ];
    $form['favicon_source_svg'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['favicon_source_svg'],
    ];
    $form['favicon_source_filename'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['favicon_source_filename'],
    ];
    $form['favicon_theme_color'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['favicon_android_background_color'],
    ];
    $form['favicon_manifest_name'] = [
      '#type' => 'hidden',
      '#default_value' => $site_name,
    ];
    $form['favicon_manifest_display'] = [
      '#type' => 'hidden',
      '#default_value' => 'standalone',
    ];
    $form['favicon_android_maskable_enabled'] = [
      '#type' => 'hidden',
      '#value' => 1,
    ];

    $form['emulsify_favicon'] = [
      '#type' => 'details',
      '#title' => $this->t('Favicon'),
      '#open' => TRUE,
      '#description' => $this->t('Upload one source SVG, configure platform-specific framing, and save the form to generate a modern favicon package. The sanitized SVG source is stored with theme settings so configuration imports can regenerate the package in other environments.'),
    ];

    $form['emulsify_favicon']['favicon_package_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable generated favicon package'),
      '#default_value' => $settings['favicon_package_enabled'],
      '#description' => $this->t('If checked, Emulsify will attach generated favicon, Apple touch icon, and manifest tags to every page.'),
    ];

    $form['emulsify_favicon']['source'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="favicon_package_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['emulsify_favicon']['source']['favicon_source_fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Icon File'),
      '#default_value' => $settings['favicon_source_fid'],
      '#progress_indicator' => 'bar',
      '#progress_message' => $this->t('Uploading icon file...'),
      '#upload_location' => 'public://favicon-source/' . $theme_name,
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'svg',
        ],
      ],
      '#description' => $this->t('Use a square SVG with a square viewBox. Embedded base64 raster image data is allowed, but it may scale less cleanly than a pure vector source.'),
    ];
    $form['emulsify_favicon']['source']['portable_source'] = [
      '#type' => 'item',
      '#title' => $this->t('Portable source config'),
      '#markup' => '<span class="description">' . $this->buildPortableSourceDescription($package_status) . '</span>',
    ];
    if (!$has_generated_package) {
      $form['emulsify_favicon']['source']['generation_hint'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-favicon-generation-hint' => 'true',
          'hidden' => 'hidden',
        ],
        '#markup' => '<div class="messages messages--warning" role="status">' . $this->t('Save the form or click Generate package after selecting an icon file to build the favicon package and unlock the saved asset previews.') . '</div>',
        '#states' => [
          'visible' => [
            ':input[name="favicon_package_enabled"]' => ['checked' => TRUE],
            ':input[name="favicon_source_fid[fids]"]' => ['filled' => TRUE],
          ],
        ],
      ];
    }
    if ($package_status['source_diagnostics'] !== '') {
      $form['emulsify_favicon']['source']['diagnostics'] = [
        '#type' => 'item',
        '#title' => $this->t('Source diagnostics'),
        '#markup' => $package_status['source_diagnostics'],
      ];
    }

    $form['emulsify_favicon']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Current generated package'),
      '#markup' => $package_status['status_markup'],
    ];

    $form['emulsify_favicon']['browser'] = [
      '#type' => 'details',
      '#title' => $this->t('Browser favicon settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="favicon_package_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['emulsify_favicon']['browser']['favicon_background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Icon Background Color'),
      '#default_value' => $settings['favicon_background_color'],
      '#description' => $this->t('Applied to the square browser favicon, SVG favicon, and ICO.'),
    ];
    if ($has_preview_source) {
      $form['emulsify_favicon']['browser']['preview'] = $this->previewBuilder->buildBrowserPreview($preview_settings, $source_file);
    }

    $form['emulsify_favicon']['ios'] = [
      '#type' => 'details',
      '#title' => $this->t('iOS icon settings'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="favicon_package_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['emulsify_favicon']['ios']['favicon_ios_background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Icon Background Color'),
      '#default_value' => $settings['favicon_ios_background_color'],
      '#description' => $this->t('Apple touch icons should use an opaque background.'),
    ];
    $form['emulsify_favicon']['ios']['favicon_ios_padding'] = [
      '#type' => 'number',
      '#title' => $this->t('Icon Padding'),
      '#default_value' => $settings['favicon_ios_padding'],
      '#min' => 0,
      '#max' => 40,
      '#description' => $this->t('Padding is measured as a percentage of the icon canvas on each edge. The default keeps logos away from rounded corners.'),
    ];
    $form['emulsify_favicon']['ios']['favicon_ios_icon_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Name'),
      '#default_value' => $settings['favicon_ios_icon_name'],
      '#maxlength' => 60,
      '#description' => $this->t('Used for the iOS shortcut label. Leave blank to use the current site name.'),
    ];
    if ($has_preview_source) {
      $form['emulsify_favicon']['ios']['preview'] = $this->previewBuilder->buildIosPreview($preview_settings, $source_file);
    }

    $form['emulsify_favicon']['android'] = [
      '#type' => 'details',
      '#title' => $this->t('Android icon settings'),
      '#open' => FALSE,
      '#description' => $this->t('The generated web app manifest uses standalone display mode.'),
      '#states' => [
        'visible' => [
          ':input[name="favicon_package_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['emulsify_favicon']['android']['favicon_android_background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Icon Background Color'),
      '#default_value' => $settings['favicon_android_background_color'],
      '#description' => $this->t('Used for the Android icon background and the generated theme-color metadata.'),
    ];
    $form['emulsify_favicon']['android']['favicon_android_padding'] = [
      '#type' => 'number',
      '#title' => $this->t('Icon Padding'),
      '#default_value' => $settings['favicon_android_padding'],
      '#min' => 0,
      '#max' => 40,
      '#description' => $this->t('The maskable icon keeps at least 20% padding to protect the safe area.'),
    ];
    $form['emulsify_favicon']['android']['maskable_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Maskable Android Icon'),
      '#markup' => '<span class="description">' . $this->t('Emulsify always generates a dedicated maskable Android icon with extra safe-area padding so launchers can crop the outer edges into circles or squircles without cutting into the logo.') . '</span>',
    ];
    $form['emulsify_favicon']['android']['favicon_manifest_short_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Name'),
      '#default_value' => $settings['favicon_manifest_short_name'],
      '#maxlength' => 60,
      '#description' => $this->t('Used for the Android and PWA launcher label. Leave blank to use the current site name.'),
    ];
    if ($has_preview_source) {
      $form['emulsify_favicon']['android']['preview'] = $this->previewBuilder->buildAndroidPreview($preview_settings, $source_file);
    }

    if ($package_status['source_available'] || $has_generated_package) {
      $form['emulsify_favicon']['actions'] = [
        '#type' => 'actions',
      ];

      if ($package_status['source_available']) {
        $default_label = $package_status['state'] === 'current'
          ? $this->t('Regenerate package')
          : $this->t('Generate package');
        $dirty_label = $package_status['state'] === 'current'
          ? $this->t('Regenerate package (changes pending)')
          : $this->t('Generate package (changes pending)');

        $form['emulsify_favicon']['actions']['regenerate_package'] = [
          '#type' => 'submit',
          '#value' => $default_label,
          '#submit' => [[self::class, 'requestFaviconRegeneration']],
          '#button_type' => $package_status['state'] === 'current' ? 'secondary' : 'primary',
          '#attributes' => [
            'data-favicon-regenerate-button' => 'true',
            'data-default-label' => (string) $default_label,
            'data-dirty-label' => (string) $dirty_label,
          ],
        ];
      }

      if ($has_generated_package) {
        $form['emulsify_favicon']['actions']['reset_package'] = [
          '#type' => 'submit',
          '#value' => $this->t('Reset to theme default'),
          '#limit_validation_errors' => [],
          '#submit' => [[self::class, 'resetFaviconSettings']],
          '#button_type' => 'secondary',
        ];
      }

      $form['emulsify_favicon']['actions']['dirty_state'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-favicon-dirty-state' => 'true',
          'hidden' => 'hidden',
        ],
        '#markup' => '<div class="messages messages--warning" role="status">' . $this->t('The source SVG or package settings changed. Save the form or click the package button again to rebuild the generated assets.') . '</div>',
      ];
    }
  }

  /**
   * Static form validate callback.
   */
  public static function validateFaviconSettings(array &$form, FormStateInterface $form_state): void {
    self::service()->doValidateFaviconSettings($form_state);
  }

  /**
   * Static form submit callback.
   */
  public static function submitFaviconSettings(array &$form, FormStateInterface $form_state): void {
    self::service()->doSubmitFaviconSettings($form_state);
  }

  /**
   * Static generate button callback.
   */
  public static function requestFaviconRegeneration(array &$form, FormStateInterface $form_state): void {
    self::service()->doRequestFaviconRegeneration($form_state);
  }

  /**
   * Static reset button callback.
   */
  public static function resetFaviconSettings(array &$form, FormStateInterface $form_state): void {
    self::service()->doResetFaviconSettings($form_state);
  }

  /**
   * Flags the form submit as an explicit regeneration request.
   */
  private function doRequestFaviconRegeneration(FormStateInterface $form_state): void {
    $form_state->set('emulsify_favicon_force_generate', TRUE);
  }

  /**
   * Validates the generated favicon package settings.
   */
  private function doValidateFaviconSettings(FormStateInterface $form_state): void {
    $site_name = $this->getSiteName();
    $settings = FaviconSettings::normalize($form_state->getValues(), $site_name);
    $form_state->setValue('favicon_theme_color', $settings['favicon_android_background_color']);
    $form_state->setValue('favicon_manifest_name', $site_name);
    $form_state->setValue('favicon_manifest_display', 'standalone');

    try {
      $source_context = $this->resolveSourceContext(
        $settings,
        !empty($settings['favicon_package_enabled']),
      );
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('favicon_source_fid', $this->t('@message', ['@message' => $exception->getMessage()]));
      return;
    }

    if ($source_context !== []) {
      $form_state->setValue('favicon_source_svg', $source_context['source_svg']);
      $form_state->setValue('favicon_source_filename', $source_context['source_filename']);
    }

    if (empty($settings['favicon_package_enabled'])) {
      return;
    }

    if ($source_context === []) {
      $form_state->setErrorByName('favicon_source_fid', $this->t('Upload an SVG icon file before enabling the generated favicon package.'));
    }
  }

  /**
   * Generates the favicon package when the theme settings form is saved.
   */
  private function doSubmitFaviconSettings(FormStateInterface $form_state): void {
    if ($form_state->get('emulsify_favicon_reset')) {
      return;
    }

    $theme_name = FaviconSettings::resolveThemeName($form_state);
    $settings = FaviconSettings::normalize($form_state->getValues(), $this->getSiteName());

    try {
      $source_context = $this->resolveSourceContext(
        $settings,
        !empty($settings['favicon_package_enabled']),
      );
    }
    catch (\InvalidArgumentException) {
      return;
    }

    if ($source_context !== []) {
      if ($source_context['source_file'] instanceof File) {
        $source_context['source_file']->setPermanent();
        $source_context['source_file']->save();
      }

      $form_state->setValue('favicon_source_svg', $source_context['source_svg']);
      $form_state->setValue('favicon_source_filename', $source_context['source_filename']);
    }

    if (empty($settings['favicon_package_enabled']) || $source_context === []) {
      return;
    }

    try {
      $definition = $this->packageGenerator->getPackageDefinition(
        $theme_name,
        $settings,
        $source_context['source_svg'],
      );
      $metadata = $this->packageGenerator->readPackageMetadata($definition['path']);
      $should_generate = (bool) $form_state->get('emulsify_favicon_force_generate')
        || $settings['favicon_package_hash'] !== $definition['hash']
        || $settings['favicon_package_path'] !== $definition['path']
        || !$this->packageGenerator->packageExists($definition['path']);

      $form_state->setValue('favicon_package_hash', $definition['hash']);
      $form_state->setValue('favicon_package_path', $definition['path']);
      if (is_array($metadata) && isset($metadata['generated_at'])) {
        $form_state->setValue('favicon_package_generated_at', (int) $metadata['generated_at']);
      }

      if (!$should_generate) {
        return;
      }

      $result = $this->packageGenerator->generateFromSvg(
        $theme_name,
        $source_context['source_svg'],
        $settings,
        [
          'file_id' => $source_context['source_file'] instanceof File ? (int) $source_context['source_file']->id() : 0,
          'filename' => $source_context['source_filename'],
        ],
      );
      $form_state->setValue('favicon_package_hash', $result['hash']);
      $form_state->setValue('favicon_package_path', $result['path']);
      $form_state->setValue('favicon_package_generated_at', $result['generated_at']);
      $this->messenger->addStatus($this->t('Generated the favicon package for %theme.', ['%theme' => $theme_name]));
    }
    catch (\Throwable $exception) {
      $this->logger->error('Favicon generation failed for %theme: @message', [
        '%theme' => $theme_name,
        '@message' => $exception->getMessage(),
      ]);
      $this->messenger->addError($this->t('Unable to generate the favicon package. @message', ['@message' => $exception->getMessage()]));
    }
  }

  /**
   * Resets the generated favicon package and falls back to the theme default.
   */
  private function doResetFaviconSettings(FormStateInterface $form_state): void {
    $form_state->set('emulsify_favicon_reset', TRUE);

    $theme_name = FaviconSettings::resolveThemeName($form_state);
    $settings = FaviconSettings::loadThemeSettings($theme_name, $this->themeSettingsProvider, $this->getSiteName());
    $source_file = $this->resolveStoredSourceFile($settings);
    $package_status = $this->buildPackageStatus($theme_name, $settings, $source_file);

    $package_paths = array_unique(array_filter([
      $settings['favicon_package_path'],
      $package_status['path'],
    ]));
    foreach ($package_paths as $package_path) {
      $realpath = $this->fileSystem->realpath($package_path);
      if ($realpath && is_dir($realpath)) {
        $this->fileSystem->deleteRecursive($realpath);
      }
    }

    $config = $this->configFactory->getEditable($theme_name . '.settings');
    foreach (FaviconSettings::DEFAULTS as $key => $value) {
      $config->set($key, $value);
      $form_state->setValue($key, $value);
    }
    $config
      ->set('features.favicon', TRUE)
      ->set('favicon.use_default', TRUE)
      ->set('favicon.path', '')
      ->save();

    $this->cacheTagsInvalidator->invalidateTags(['rendered']);
    $this->messenger->addStatus($this->t('Reset the generated favicon package and restored the theme default favicon behavior.'));
  }

  /**
   * Resolves a stored managed file source when it still exists.
   */
  private function resolveStoredSourceFile(array $settings): ?File {
    $source_fid = FaviconSettings::getSourceFileId($settings);
    if (!$source_fid) {
      return NULL;
    }

    $source_file = File::load($source_fid);
    return $source_file instanceof File ? $source_file : NULL;
  }

  /**
   * Resolves the current source context from form or config values.
   *
   * @return array<string, mixed>
   *   The resolved source context, or an empty array if no source exists.
   */
  private function resolveSourceContext(array $settings, bool $requires_rasterization): array {
    $source_svg = FaviconSettings::getSourceSvg($settings);
    $source_filename = (string) ($settings['favicon_source_filename'] ?: 'favicon.svg');
    $source_fid = FaviconSettings::getSourceFileId($settings);
    $source_file = $source_fid ? File::load($source_fid) : NULL;
    if ($source_fid && !$source_file && $source_svg === '') {
      throw new \InvalidArgumentException('The uploaded icon file could not be loaded.');
    }

    $analysis = $source_file
      ? $this->resolveSourceFileAnalysis($source_file, $source_svg, $requires_rasterization)
      : NULL;

    if ($analysis !== NULL) {
      $source_filename = $source_file->getFilename();
    }

    if ($analysis === NULL && $source_svg === '') {
      return [];
    }

    if ($analysis === NULL) {
      $source_file = NULL;
      $analysis = $this->packageGenerator->validateSourceSvg($source_svg, $requires_rasterization);
    }

    return [
      'source_file' => $source_file,
      'source_svg' => (string) $analysis['sanitized_svg'],
      'source_filename' => $source_filename,
      'analysis' => $analysis,
    ];
  }

  /**
   * Validates a managed source file or falls back to stored SVG config.
   *
   * @return array<string, mixed>|null
   *   The validated source analysis, or NULL when config fallback should be used.
   */
  private function resolveSourceFileAnalysis(File $source_file, string $source_svg, bool $requires_rasterization): ?array {
    try {
      return $this->packageGenerator->validateSourceFile($source_file, $requires_rasterization);
    }
    catch (\InvalidArgumentException $exception) {
      if ($source_svg === '') { throw $exception; }
    }

    return NULL;
  }

  /**
   * Builds package freshness and source diagnostics for the admin UI.
   *
   * @return array<string, mixed>
   *   Status and diagnostic metadata for the current theme.
   */
  private function buildPackageStatus(string $theme_name, array $settings, ?File $source_file): array {
    $status = [
      'state' => 'missing',
      'hash' => (string) ($settings['favicon_package_hash'] ?? ''),
      'path' => (string) ($settings['favicon_package_path'] ?? ''),
      'package_exists' => !empty($settings['favicon_package_path']) && $this->packageGenerator->packageExists($settings['favicon_package_path']),
      'source_available' => FALSE,
      'portable_source_missing' => FALSE,
      'exportable_source' => FaviconSettings::hasExportableSource($settings),
      'source_diagnostics' => '',
      'status_markup' => '',
    ];

    try {
      $source_context = [];
      if ($source_file) {
        $source_context = $this->resolveSourceContext($settings, FALSE);
        $status['portable_source_missing'] = !$status['exportable_source'];
      }
      elseif ($status['exportable_source']) {
        $source_context = $this->resolveSourceContext($settings, FALSE);
      }

      if ($source_context !== []) {
        $status['source_available'] = TRUE;
        $definition = $this->packageGenerator->getPackageDefinition(
          $theme_name,
          $settings,
          $source_context['source_svg'],
        );
        $status['hash'] = $definition['hash'];
        $status['path'] = $definition['path'];
        $status['package_exists'] = $this->packageGenerator->packageExists($definition['path']);
        $status['source_diagnostics'] = $this->buildSourceDiagnosticsMarkup(
          $source_context['analysis']['warnings'] ?? [],
          $status['portable_source_missing'],
        );

        if ($status['package_exists'] && $settings['favicon_package_hash'] === $definition['hash'] && $settings['favicon_package_path'] === $definition['path']) {
          $status['state'] = 'current';
        }
        elseif ($status['package_exists']) {
          $status['state'] = 'stale';
        }
        else {
          $status['state'] = 'missing';
        }
      }
      elseif ($status['package_exists']) {
        $status['state'] = 'legacy';
        $status['source_diagnostics'] = $this->buildSourceDiagnosticsMarkup([], TRUE);
      }
    }
    catch (\Throwable $exception) {
      $status['state'] = 'invalid';
      $status['source_diagnostics'] = '<div class="messages messages--error" role="alert"><div>' . Html::escape($exception->getMessage()) . '</div></div>';
    }

    $status['status_markup'] = $this->buildStatusMarkup($status);
    return $status;
  }

  /**
   * Describes whether the current source is portable through config.
   */
  private function buildPortableSourceDescription(array $package_status): string {
    if ($package_status['exportable_source']) {
      return (string) $this->t('The sanitized SVG source is saved in theme config and can be regenerated after configuration import.');
    }

    if ($package_status['portable_source_missing']) {
      return (string) $this->t('Save this form once to store a portable copy of the current SVG source in theme config.');
    }

    return (string) $this->t('Saving an SVG source also stores a portable copy in theme config for other environments.');
  }

  /**
   * Builds source warning markup.
   *
   * @param string[] $warnings
   *   Source warnings to render.
   */
  private function buildSourceDiagnosticsMarkup(array $warnings, bool $portable_source_missing): string {
    $items = [];
    foreach ($warnings as $warning) {
      $items[] = '<li>' . Html::escape($warning) . '</li>';
    }
    if ($portable_source_missing) {
      $items[] = '<li>' . Html::escape((string) $this->t('Save this form once to store a portable SVG source in theme config for future configuration exports.')) . '</li>';
    }

    if ($items === []) {
      return '';
    }

    return '<div class="messages messages--warning" role="status"><ul>' . implode('', $items) . '</ul></div>';
  }

  /**
   * Builds package freshness markup.
   */
  private function buildStatusMarkup(array $status): string {
    $path = $status['path'] !== ''
      ? $status['path'] . ($status['hash'] !== '' ? ' (' . $status['hash'] . ')' : '')
      : '';

    return match ($status['state']) {
      'current' => '<div class="messages messages--status" role="status"><div>' . $this->t('Generated package is current: @path', ['@path' => $path]) . '</div></div>',
      'stale' => '<div class="messages messages--warning" role="status"><div>' . $this->t('Generated package is out of date. Save the form or click Regenerate package to rebuild @path from the latest source and platform settings.', ['@path' => $path]) . '</div></div>',
      'legacy' => '<div class="messages messages--warning" role="status"><div>' . $this->t('A generated package exists, but no portable SVG source is saved in theme config yet. Save this form once to make configuration exports portable.') . '</div></div>',
      'invalid' => '<div class="messages messages--error" role="alert"><div>' . $this->t('The saved SVG source is invalid and cannot currently generate a favicon package.') . '</div></div>',
      default => '<div class="messages messages--warning" role="status"><div>' . $this->t('No generated package exists for this environment yet. Save the form or click Generate package to build it from the saved SVG source and platform settings.') . '</div></div>',
    };
  }

  /**
   * Returns the current site name.
   */
  private function getSiteName(): string {
    return (string) $this->configFactory->get('system.site')->get('name');
  }

  /**
   * Resolves the autowired hook service for static FAPI callbacks.
   */
  private static function service(): self {
    return \Drupal::service('class_resolver')->getInstanceFromDefinition(self::class);
  }

}
