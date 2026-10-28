<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Configures a shared raster image independently of favicon packages.
 */
final class SocialShareSettingsForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Creates the social sharing settings form helper.
   */
  public function __construct(
    private readonly ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly FileUsageInterface $fileUsage,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(ThemeSettingsProvider::class),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('file_url_generator'),
      $container->get('file.usage'),
      $container->get('request_stack'),
    );
  }

  /**
   * Adds the enable control and optional image inputs to theme settings.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $theme = FaviconSettings::resolveThemeName($form_state);
    $settings = SocialShareSettings::loadThemeSettings($theme, $this->themeSettingsProvider);
    $form_state->set('emulsify_social_share_existing', $settings);
    $form['#attached']['library'][] = 'emulsify/social_share_admin';
    $form['#validate'][] = [self::class, 'validateSocialShareSettings'];
    $form['#submit'][] = [self::class, 'submitSocialShareSettings'];
    $form['emulsify_social_share_image'] = [
      '#type' => 'details',
      '#title' => $this->t('Social share image'),
      '#open' => FALSE,
      '#description' => $this->t('Choose a fallback image for links shared on social platforms. Page-specific social metadata, including Metatag images and cards, takes precedence.'),
    ];
    $section = &$form['emulsify_social_share_image'];
    $section['social_share_image_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable social share image'),
      '#default_value' => $settings['social_share_image_enabled'],
    ];
    $section['settings'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="social_share_image_enabled"]' => ['checked' => TRUE]]],
    ];
    $fields = &$section['settings'];
    $fields['social_share_image_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to social share image'),
      '#default_value' => $settings['social_share_image_path'],
      '#description' => $this->t('Use a public:// URI, a path relative to the public files directory, or a path inside the Drupal web root, such as themes/custom/my_theme/images/share.jpg. Remote URLs and private files are not supported.'),
    ];
    $fields['social_share_image_fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload social share image'),
      '#default_value' => $settings['social_share_image_fid'],
      '#upload_location' => 'public://social-share-image/' . $theme,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png webp gif'],
        'FileIsImage' => [],
      ],
      '#description' => $this->t('Upload a JPG, PNG, WebP, or GIF. A new upload replaces the path above; a path to another image replaces the saved upload. Paths to the same image retain its managed upload. A 1200 × 630 image works well for large social cards. Save the form to update the preview.'),
    ];
    $fields['social_share_image_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image alternative text'),
      '#default_value' => $settings['social_share_image_alt'],
      '#description' => $this->t('Describe the image for people who cannot see it. Leave blank to use the site name.'),
    ];
    try {
      $image = (new SocialShareImageResolver($this->fileSystem))->resolve($settings);
    }
    catch (\InvalidArgumentException) {
      $image = [];
    }
    $site = $this->configFactory->get('system.site');
    $request = $this->requestStack->getCurrentRequest();
    $fields['preview'] = (new SocialSharePreviewBuilder())->build(
      $settings,
      $image === [] ? '' : $this->fileUrlGenerator->generateAbsoluteString($image['uri']),
      $image['info'] ?? [],
      (string) $site->get('name'),
      (string) $site->get('slogan'),
      $request ? $request->getSchemeAndHttpHost() . $request->getBasePath() : '',
    );
  }

  /**
   * Validates and normalizes values before core persists theme settings.
   */
  public static function validateSocialShareSettings(array &$form, FormStateInterface $form_state): void {
    self::service()->validate($form_state);
  }

  /**
   * Retains managed uploads and releases replaced image usage.
   */
  public static function submitSocialShareSettings(array &$form, FormStateInterface $form_state): void {
    self::service()->submit($form_state);
  }

  /**
   * Resolves upload/path precedence and reports errors on the selected input.
   */
  private function validate(FormStateInterface $form_state): void {
    $parents = $form_state->getTriggeringElement()['#array_parents'] ?? [];
    if (in_array('social_share_image_fid', $parents, TRUE) && in_array(end($parents), ['upload_button', 'remove_button'], TRUE)) {
      return;
    }
    $settings = SocialShareSettings::normalize($form_state->getValues());
    $old = $form_state->get('emulsify_social_share_existing') ?? SocialShareSettings::DEFAULTS;
    $new_upload = $settings['social_share_image_fid'] !== [] && $settings['social_share_image_fid'] !== $old['social_share_image_fid'];
    if ($new_upload) {
      $settings['social_share_image_path'] = '';
    }
    elseif ($settings['social_share_image_path'] !== $old['social_share_image_path']) {
      $settings['social_share_image_fid'] = [];
    }
    elseif ($settings['social_share_image_fid'] === [] && $old['social_share_image_fid'] !== []) {
      $settings['social_share_image_path'] = '';
    }
    $check_previous_upload = $old['social_share_image_fid'] !== [] && $settings['social_share_image_fid'] === [] && $settings['social_share_image_path'] !== '';
    if ($settings['social_share_image_enabled'] || $new_upload || $check_previous_upload) {
      try {
        $image = (new SocialShareImageResolver($this->fileSystem))->resolve($settings);
        if ($image === []) {
          throw new \InvalidArgumentException('Upload an image or enter a valid image path before enabling the social share image.');
        }
        $settings['social_share_image_path'] = $image['uri'];
        if ($check_previous_upload) {
          $previous = File::load($old['social_share_image_fid'][0]);
          // Keep the managed reference when a public URI and web-root path
          // identify the same file, so unused-file cleanup cannot delete it.
          if ($previous && $this->fileSystem->realpath($previous->getFileUri()) === $image['realpath']) {
            $settings['social_share_image_fid'] = $old['social_share_image_fid'];
          }
        }
      }
      catch (\InvalidArgumentException $exception) {
        if ($settings['social_share_image_enabled'] || $new_upload) {
          $key = $settings['social_share_image_fid'] !== [] ? 'social_share_image_fid' : 'social_share_image_path';
          $form_state->setErrorByName($key, $this->t('@message', ['@message' => $exception->getMessage()]));
        }
      }
    }
    foreach ($settings as $key => $value) {
      $form_state->setValue($key, $value);
    }
  }

  /**
   * Keeps the selected upload permanent while it is referenced by this theme.
   */
  private function submit(FormStateInterface $form_state): void {
    $theme = FaviconSettings::resolveThemeName($form_state);
    $settings = SocialShareSettings::normalize($form_state->getValues());
    $old = $form_state->get('emulsify_social_share_existing') ?? SocialShareSettings::DEFAULTS;
    $file = $settings['social_share_image_fid'] ? File::load($settings['social_share_image_fid'][0]) : NULL;
    if ($file) {
      $usage = $this->fileUsage->listUsage($file);
      if (empty($usage['emulsify']['social_share_image'][$theme])) {
        $this->fileUsage->add($file, 'emulsify', 'social_share_image', $theme);
      }
    }
    if ($old['social_share_image_fid'] !== $settings['social_share_image_fid'] && $old['social_share_image_fid'] !== []) {
      $old_file = File::load($old['social_share_image_fid'][0]);
      if ($old_file) {
        $this->fileUsage->delete($old_file, 'emulsify', 'social_share_image', $theme, 0);
      }
    }
  }

  /**
   * Resolves the autowired helper for static form callbacks.
   */
  private static function service(): self {
    return \Drupal::service('class_resolver')->getInstanceFromDefinition(self::class);
  }

}
