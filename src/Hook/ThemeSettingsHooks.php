<?php

namespace Drupal\emulsify\Hook;

use Drupal\Component\Datetime\TimeInterface;
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
    $has_generated_package = !empty($settings['favicon_package_path']);
    $source_file = NULL;
    if ($source_fid = FaviconSettings::getSourceFileId($settings)) {
      $source_file = File::load($source_fid);
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
      '#description' => $this->t('Upload one source SVG, configure platform-specific framing, and save the form to generate a modern favicon package. When enabled, the generated package overrides Drupal\'s default shortcut icon tags.'),
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
      '#description' => $this->t('Use a square SVG. Embedded base64 image data is allowed, but remote references and scripts are stripped during generation.'),
    ];
    if (!$has_generated_package) {
      $form['emulsify_favicon']['source']['generation_hint'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-favicon-generation-hint' => 'true',
          'hidden' => 'hidden',
        ],
        '#markup' => '<div class="messages messages--warning" role="status">' . $this->t('Save the form after selecting an icon file to generate the favicon package and unlock the platform previews below.') . '</div>',
        '#states' => [
          'visible' => [
            ':input[name="favicon_package_enabled"]' => ['checked' => TRUE],
            ':input[name="favicon_source_fid[fids]"]' => ['filled' => TRUE],
          ],
        ],
      ];
    }

    $form['emulsify_favicon']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Current generated package'),
      '#markup' => '<span class="description">' . $this->t('@summary', ['@summary' => FaviconSettings::summarizePackageLocation($settings)]) . '</span>',
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
    if ($has_generated_package) {
      $form['emulsify_favicon']['browser']['preview'] = $this->previewBuilder->buildBrowserPreview($settings, $source_file);
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
    if ($has_generated_package) {
      $form['emulsify_favicon']['ios']['preview'] = $this->previewBuilder->buildIosPreview($settings, $source_file);
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
    if ($has_generated_package) {
      $form['emulsify_favicon']['android']['preview'] = $this->previewBuilder->buildAndroidPreview($settings, $source_file);
    }

    if ($has_generated_package) {
      $form['emulsify_favicon']['actions'] = [
        '#type' => 'actions',
      ];
      $form['emulsify_favicon']['actions']['reset_package'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset to theme default'),
        '#limit_validation_errors' => [],
        '#submit' => [[self::class, 'resetFaviconSettings']],
        '#button_type' => 'secondary',
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
   * Validates the generated favicon package settings.
   */
  private function doValidateFaviconSettings(FormStateInterface $form_state): void {
    $site_name = $this->getSiteName();
    $settings = FaviconSettings::normalize($form_state->getValues(), $site_name);
    $form_state->setValue('favicon_theme_color', $settings['favicon_android_background_color']);
    $form_state->setValue('favicon_manifest_name', $site_name);
    $form_state->setValue('favicon_manifest_display', 'standalone');
    if (empty($settings['favicon_package_enabled'])) {
      return;
    }

    $source_fid = FaviconSettings::getSourceFileId($settings);
    if (!$source_fid) {
      $form_state->setErrorByName('favicon_source_fid', $this->t('Upload an SVG icon file before enabling the generated favicon package.'));
      return;
    }

    $source_file = File::load($source_fid);
    if (!$source_file) {
      $form_state->setErrorByName('favicon_source_fid', $this->t('The uploaded icon file could not be loaded.'));
      return;
    }

    try {
      $this->packageGenerator->validateSourceFile($source_file, TRUE);
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('favicon_source_fid', $this->t('@message', ['@message' => $exception->getMessage()]));
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
    $source_file = NULL;

    if ($source_fid = FaviconSettings::getSourceFileId($settings)) {
      $source_file = File::load($source_fid);
      if ($source_file) {
        $source_file->setPermanent();
        $source_file->save();
      }
    }

    if (empty($settings['favicon_package_enabled']) || !$source_file) {
      return;
    }

    try {
      $result = $this->packageGenerator->generate($theme_name, $source_file, $settings);
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

    if (!empty($settings['favicon_package_path'])) {
      $realpath = $this->fileSystem->realpath($settings['favicon_package_path']);
      if ($realpath && is_dir($realpath)) {
        $this->fileSystem->deleteRecursive($realpath);
      }
    }

    $this->configFactory->getEditable($theme_name . '.settings')
      ->set('favicon_package_enabled', FALSE)
      ->set('favicon_package_hash', '')
      ->set('favicon_package_path', '')
      ->set('favicon_package_generated_at', 0)
      ->set('features.favicon', TRUE)
      ->set('favicon.use_default', TRUE)
      ->set('favicon.path', '')
      ->save();

    $form_state->setValue('favicon_package_enabled', FALSE);
    $form_state->setValue('favicon_package_hash', '');
    $form_state->setValue('favicon_package_path', '');
    $form_state->setValue('favicon_package_generated_at', 0);
    $this->cacheTagsInvalidator->invalidateTags(['rendered']);
    $this->messenger->addStatus($this->t('Reset the generated favicon package and restored the theme default favicon behavior.'));
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
