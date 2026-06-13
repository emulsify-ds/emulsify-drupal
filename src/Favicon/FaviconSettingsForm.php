<?php

declare(strict_types=1);

namespace Drupal\emulsify\Favicon;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds and processes the Emulsify favicon theme settings UI.
 */
final class FaviconSettingsForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Theme settings UI keys that should never be persisted to config.
   *
   * ThemeSettingsForm saves arbitrary submitted values unless they are marked
   * as clean, so all helper-only UI keys live here.
   */
  private const NON_CONFIG_THEME_SETTING_KEYS = [
    'portable_source',
    'portable_source_notice',
    'diagnostics',
    'source_diagnostics_notice',
    'status',
    'package_status_notice',
    'maskable_info',
    'maskable_icon_notice',
    'generation_hint',
    'dirty_state',
    'emulsify_tools_apply_admin_theme_favicon',
  ];

  /**
   * Theme setting keys whose changes require package generation.
   */
  private const PACKAGE_INPUT_KEYS = [
    'favicon_package_enabled',
    'favicon_source_fid',
    'favicon_source_svg',
    'favicon_source_filename',
    'favicon_background_color',
    'favicon_theme_color',
    'favicon_ios_background_color',
    'favicon_ios_padding',
    'favicon_ios_icon_name',
    'favicon_android_background_color',
    'favicon_android_padding',
    'favicon_android_maskable_enabled',
    'favicon_manifest_name',
    'favicon_manifest_short_name',
    'favicon_manifest_display',
  ];

  /**
   * Generates favicon packages from uploaded theme assets.
   */
  private FaviconThemeManager $faviconThemeManager;

  /**
   * Builds admin previews for generated favicon assets.
   */
  private FaviconPreviewBuilder $previewBuilder;

  /**
   * Logger channel for theme-settings generation failures.
   */
  private LoggerInterface $logger;

  /**
   * Creates the favicon settings form helper.
   */
  public function __construct(
    ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly MessengerInterface $messenger,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    FileUrlGeneratorInterface $fileUrlGenerator,
    TimeInterface $time,
    #[Autowire(service: 'lock')]
    LockBackendInterface $lock,
  ) {
    $this->faviconThemeManager = new FaviconThemeManager(
      $themeSettingsProvider,
      $configFactory,
      $fileSystem,
      $cacheTagsInvalidator,
      $fileUrlGenerator,
      $time,
      $lock,
    );
    $this->previewBuilder = new FaviconPreviewBuilder($fileUrlGenerator);
    $this->logger = $loggerChannelFactory->get('emulsify');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(ThemeSettingsProvider::class),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('cache_tags.invalidator'),
      $container->get('messenger'),
      $container->get('logger.factory'),
      $container->get('file_url_generator'),
      $container->get('datetime.time'),
      $container->get('lock'),
    );
  }

  /**
   * Alters the system theme settings form with favicon package controls.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $site_name = $this->getSiteName();
    $theme_name = FaviconSettings::resolveThemeName($form_state);
    $settings = $this->faviconThemeManager->loadThemeSettings($theme_name);
    $source_file = $this->faviconThemeManager->resolveStoredSourceFile($settings);
    $package_status = $this->buildPackageStatus($theme_name, $settings, $source_file);
    $has_generated_package = $package_status['package_exists'];
    $has_preview_source = $package_status['source_available'] || $has_generated_package;
    $preview_settings = $settings;
    if ($package_status['path'] !== '') {
      $preview_settings['favicon_package_path'] = $package_status['path'];
    }

    // Remove the legacy page-element display toggles and persist the values
    // the parent theme expects instead of exposing conflicting controls.
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
    $this->registerCleanValueKeys($form_state);

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
      // phpcs:ignore Drupal.Files.LineLength.TooLong
      '#description' => $this->t('Upload one source SVG, configure platform-specific framing, and save the form to generate a modern favicon package. The sanitized SVG source is stored with theme settings so configuration imports can regenerate the package in other environments. Admin saves are the theme UI path. On deploy, use Emulsify Tools to run drush emulsify_tools:favicon-generate [theme_name] after config import. Runtime page requests do not generate favicon files.'),
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
      '#description' => $this->t('Use an SVG icon file. Non-square sources are centered on a square canvas. Embedded base64 raster image data is allowed, but it may scale less cleanly than a pure vector source.'),
    ];
    $form['emulsify_favicon']['source']['portable_source_notice'] = [
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
      $form['emulsify_favicon']['source']['source_diagnostics_notice'] = [
        '#type' => 'item',
        '#title' => $this->t('Source diagnostics'),
        '#markup' => $package_status['source_diagnostics'],
      ];
    }

    $form['emulsify_favicon']['package_status_notice'] = [
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
    $form['emulsify_favicon']['android']['maskable_icon_notice'] = [
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
          '#name' => 'regenerate_package',
          '#value' => $default_label,
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
   * Static reset button callback.
   */
  public static function resetFaviconSettings(array &$form, FormStateInterface $form_state): void {
    self::service()->doResetFaviconSettings($form_state);
  }

  /**
   * Marks helper-only values so ThemeSettingsForm does not export them.
   */
  private function registerCleanValueKeys(FormStateInterface $form_state): void {
    foreach (self::NON_CONFIG_THEME_SETTING_KEYS as $key) {
      $form_state->addCleanValueKey($key);
    }
  }

  /**
   * Validates the generated favicon package settings.
   */
  private function doValidateFaviconSettings(FormStateInterface $form_state): void {
    if ($this->isFaviconSourceRemoveRequest($form_state)) {
      return;
    }

    $site_name = $this->getSiteName();
    $settings = FaviconSettings::normalize($form_state->getValues(), $site_name);
    $form_state->setValue('favicon_theme_color', $settings['favicon_android_background_color']);
    $form_state->setValue('favicon_manifest_name', $site_name);
    $form_state->setValue('favicon_manifest_display', 'standalone');

    try {
      $source_context = $this->faviconThemeManager->resolveSourceContext(
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
   * Returns whether the managed file remove button submitted this rebuild.
   */
  private function isFaviconSourceRemoveRequest(FormStateInterface $form_state): bool {
    $trigger = $form_state->getTriggeringElement();
    if (!is_array($trigger)) {
      return FALSE;
    }

    $array_parents = $trigger['#array_parents'] ?? [];
    return in_array('favicon_source_fid', $array_parents, TRUE)
      && end($array_parents) === 'remove_button';
  }

  /**
   * Generates the favicon package when the theme settings form is saved.
   */
  private function doSubmitFaviconSettings(FormStateInterface $form_state): void {
    if ($form_state->get('emulsify_favicon_reset')) {
      return;
    }

    $theme_name = FaviconSettings::resolveThemeName($form_state);
    $current_settings = $this->faviconThemeManager->loadThemeSettings($theme_name);
    $settings = FaviconSettings::normalize($form_state->getValues(), $this->getSiteName());

    try {
      $source_context = $this->faviconThemeManager->resolveSourceContext(
        $settings,
        !empty($settings['favicon_package_enabled']),
      );
    }
    catch (\InvalidArgumentException) {
      return;
    }

    if ($source_context !== []) {
      if ($source_context['source_file'] instanceof File) {
        try {
          $this->persistSanitizedSourceFile($source_context['source_file'], $source_context['source_svg']);
          $source_context['source_file']->setPermanent();
          $source_context['source_file']->save();
        }
        catch (\Throwable $exception) {
          $this->logger->error('Favicon source sanitization failed for %theme: @message', [
            '%theme' => $theme_name,
            '@message' => $exception->getMessage(),
          ]);
          $this->messenger->addError($this->t('Unable to save the sanitized favicon source. @message', ['@message' => $exception->getMessage()]));
          return;
        }
      }

      $form_state->setValue('favicon_source_svg', $source_context['source_svg']);
      $form_state->setValue('favicon_source_filename', $source_context['source_filename']);
      $settings['favicon_source_svg'] = $source_context['source_svg'];
      $settings['favicon_source_filename'] = $source_context['source_filename'];
    }

    if (empty($settings['favicon_package_enabled']) || $source_context === []) {
      return;
    }

    if (!$this->shouldGeneratePackage($current_settings, $settings, $form_state)) {
      return;
    }

    try {
      $generation = $this->faviconThemeManager->generatePackage(
        $theme_name,
        $settings,
        $this->isRegenerationRequest($form_state),
      );
      foreach ([
        'favicon_source_svg',
        'favicon_source_filename',
        'favicon_package_hash',
        'favicon_package_path',
        'favicon_package_generated_at',
      ] as $key) {
        $form_state->setValue($key, $generation['settings'][$key]);
      }

      if (!$generation['generated']) {
        return;
      }

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
   * Rewrites the retained managed upload with the sanitized SVG source.
   */
  private function persistSanitizedSourceFile(File $source_file, string $source_svg): void {
    $written = @file_put_contents($source_file->getFileUri(), $source_svg);
    if ($written === FALSE) {
      throw new \RuntimeException('Unable to persist the sanitized favicon source file.');
    }

    if (method_exists($source_file, 'setSize')) {
      $source_file->setSize($written);
    }
    if (method_exists($source_file, 'setMimeType')) {
      $source_file->setMimeType('image/svg+xml');
    }
  }

  /**
   * Resets the generated favicon package and falls back to the theme default.
   */
  private function doResetFaviconSettings(FormStateInterface $form_state): void {
    $form_state->set('emulsify_favicon_reset', TRUE);

    $theme_name = FaviconSettings::resolveThemeName($form_state);
    $defaults = $this->faviconThemeManager->resetThemeSettings($theme_name);
    foreach ($defaults as $key => $value) {
      $form_state->setValue($key, $value);
    }
    $this->messenger->addStatus($this->t('Reset the generated favicon package and restored the theme default favicon behavior.'));
  }

  /**
   * Returns whether the current submit was an explicit regeneration request.
   */
  private function isRegenerationRequest(FormStateInterface $form_state): bool {
    $trigger = $form_state->getTriggeringElement();
    return is_array($trigger) && ($trigger['#name'] ?? '') === 'regenerate_package';
  }

  /**
   * Returns whether this submit should create or refresh package files.
   */
  private function shouldGeneratePackage(
    array $current_settings,
    array $submitted_settings,
    FormStateInterface $form_state,
  ): bool {
    if ($this->isRegenerationRequest($form_state)) {
      return TRUE;
    }

    $changed = FALSE;
    foreach (self::PACKAGE_INPUT_KEYS as $key) {
      $changed = $changed || (($current_settings[$key] ?? NULL) !== ($submitted_settings[$key] ?? NULL));
    }

    return $changed;
  }

  /**
   * Builds package freshness and source diagnostics for the admin UI.
   *
   * @return array<string, mixed>
   *   Status and diagnostic metadata for the current theme.
   */
  private function buildPackageStatus(string $theme_name, array $settings, ?File $source_file): array {
    $status = $this->faviconThemeManager->buildPackageStatus($theme_name, $settings, $source_file);
    try {
      $status['portable_source_missing'] = $source_file instanceof File && empty($status['portable_source_available']);
      $status['source_diagnostics'] = $this->buildSourceDiagnosticsMarkup(
        $status['analysis_warnings'] ?? [],
        (bool) $status['portable_source_missing'],
        (int) ($status['portable_source_size'] ?? 0),
      );
      if (($status['state'] ?? '') === 'legacy') {
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
    if ($package_status['portable_source_available']) {
      return (string) $this->t('The sanitized SVG source is saved in theme config (@size) and can be regenerated after configuration import.', [
        '@size' => $this->formatBytes((int) $package_status['portable_source_size']),
      ]);
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
   * @param bool $portable_source_missing
   *   TRUE when config lacks a portable source copy.
   * @param int $portable_source_size
   *   Size of stored portable SVG source in bytes.
   */
  private function buildSourceDiagnosticsMarkup(array $warnings, bool $portable_source_missing, int $portable_source_size = 0): string {
    $items = [];
    foreach ($warnings as $warning) {
      $items[] = '<li>' . Html::escape($warning) . '</li>';
    }
    if ($portable_source_missing) {
      $items[] = '<li>' . Html::escape((string) $this->t('Save this form once to store a portable SVG source in theme config for future configuration exports.')) . '</li>';
    }
    if ($portable_source_size > FaviconPackageGenerator::PORTABLE_SOURCE_ADVISORY_SIZE) {
      $items[] = '<li>' . Html::escape((string) $this->t('The sanitized portable SVG stored in config is currently @size. Keep portable sources below @recommended when possible so config exports stay reviewable.', [
        '@size' => $this->formatBytes($portable_source_size),
        '@recommended' => $this->formatBytes(FaviconPackageGenerator::PORTABLE_SOURCE_ADVISORY_SIZE),
      ])) . '</li>';
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

    $status_markup = match ($status['state']) {
      'current' => $this->buildAdminMessageMarkup('status', 'status', $this->t('Generated package is current: @path', ['@path' => $path])),
      'stale' => $this->buildAdminMessageMarkup('warning', 'status', $this->t('Generated package is out of date. Save the form or click Regenerate package to rebuild @path from the latest source and platform settings before traffic depends on stale assets.', ['@path' => $path])),
      'legacy' => $this->buildAdminMessageMarkup('warning', 'status', $this->t('A generated package exists, but no portable SVG source is saved in theme config yet. Save this form once to make configuration exports portable.')),
      'invalid' => $this->buildAdminMessageMarkup('error', 'alert', $this->t('The saved SVG source is invalid and cannot currently generate a favicon package. Review the source diagnostics, upload a valid SVG, or save the form again with a sanitized source.')),
      default => $this->buildMissingPackageMarkup($status, $path),
    };

    return $status_markup . $this->buildRuntimeRequirementsMarkup($status);
  }

  /**
   * Builds package-missing diagnostics for the admin UI.
   */
  private function buildMissingPackageMarkup(array $status, string $path): string {
    if ($this->savedPackageReferenceIsMissing($status)) {
      return $this->buildAdminMessageMarkup('warning', 'status', $this->t('Theme config references a generated favicon package at @path, but those files are not present in this environment. Generated favicon files live in public files and are not created during page requests. Save this form, click Generate package, or run drush emulsify_tools:favicon-generate @theme after config import.', [
        '@path' => $path,
        '@theme' => (string) ($status['theme_name'] ?? 'emulsify'),
      ]));
    }

    return $this->buildAdminMessageMarkup('warning', 'status', $this->t('No generated package exists for the current source and settings. Save this form, click Generate package, or run drush emulsify_tools:favicon-generate @theme to build it from the saved SVG source. Page requests will not generate favicon files.', [
      '@theme' => (string) ($status['theme_name'] ?? 'emulsify'),
    ]));
  }

  /**
   * Determines whether config points at a package missing from public files.
   */
  private function savedPackageReferenceIsMissing(array $status): bool {
    return !empty($status['saved_path'])
      && empty($status['saved_package_exists'])
      && (empty($status['path']) || $status['saved_path'] === $status['path']);
  }

  /**
   * Builds extension availability diagnostics for package generation.
   */
  private function buildRuntimeRequirementsMarkup(array $status): string {
    if (empty($status['package_enabled']) || empty($status['source_available'])) {
      return '';
    }

    $requirements = $this->faviconThemeManager->getRuntimeDependencyStatus();
    $items = [];
    if (!$requirements['gd']) {
      $items[] = '<li>' . Html::escape((string) $this->t('The GD PHP extension is missing. Favicon generation needs GD to write PNG and ICO assets.')) . '</li>';
    }
    if (!$requirements['imagick']) {
      $items[] = '<li>' . Html::escape((string) $this->t('The Imagick PHP extension is missing. Favicon generation needs Imagick to rasterize SVG sources into PNG assets.')) . '</li>';
    }

    if ($items === []) {
      return '';
    }

    $items[] = '<li>' . Html::escape((string) $this->t('Enable the missing extension in the PHP environment used by Drupal admin saves and Emulsify Tools Drush favicon generation, then regenerate the package.')) . '</li>';
    return '<div class="messages messages--error" role="alert"><ul>' . implode('', $items) . '</ul></div>';
  }

  /**
   * Builds a single Drupal admin message wrapper.
   */
  private function buildAdminMessageMarkup(string $type, string $role, string|\Stringable $message): string {
    return '<div class="messages messages--' . Html::escape($type) . '" role="' . Html::escape($role) . '"><div>' . $message . '</div></div>';
  }

  /**
   * Formats a byte count for admin diagnostics.
   */
  private function formatBytes(int $bytes): string {
    if ($bytes < 1024) {
      return $bytes . ' B';
    }

    if ($bytes < 1048576) {
      return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
  }

  /**
   * Returns the current site name.
   */
  private function getSiteName(): string {
    return (string) $this->configFactory->get('system.site')->get('name');
  }

  /**
   * Resolves the autowired form helper for static FAPI callbacks.
   */
  private static function service(): self {
    return \Drupal::service('class_resolver')->getInstanceFromDefinition(self::class);
  }

}
