const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const fixture = String.raw`
namespace Drupal\Component\Utility {
  final class Html {
    public static function escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
  }
}
namespace Drupal\Core\Render {
  final class Markup {
    public static function create(string $text): string { return $text; }
  }
}
namespace Drupal\Core\StringTranslation {
  trait StringTranslationTrait {
    public function t(string $text, array $args = []): string { return strtr($text, $args); }
  }
}
namespace {
  require $argv[1];
  $builder = new \Drupal\emulsify\Social\SocialSharePreviewBuilder();
  echo json_encode($builder->build(
    ['social_share_image_alt' => 'Artwork "alt" <script>bad()</script>'],
    $argv[2],
    ['width' => 1200, 'height' => 630],
    'Site <strong>name</strong>',
    'Description & details',
    'https://example.test/page',
  ), JSON_THROW_ON_ERROR);
}`;

function previewDefinition(imageUrl = 'https://example.test/saved.png?name="image"') {
  const result = spawnSync('php', ['-r', fixture, path.join(root, 'src/Social/SocialSharePreviewBuilder.php'), imageUrl], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return JSON.parse(result.stdout);
}

// Behavior tests use native controls from the Form API definition. The Drupal
// smoke test separately verifies their actual themed rendering and config saves.
function previewMarkup(imageUrl) {
  const definition = previewDefinition(imageUrl);
  const { document } = new JSDOM().window;
  const wrapper = document.createElement('div');
  wrapper.setAttribute('data-social-share-preview', '');
  const addControls = (element) => {
    if (element['#attributes']?.['data-social-preview-control']) {
      const control = document.createElement(element['#type'] === 'textfield' ? 'input' : element['#type']);
      control.dataset.socialPreviewControl = element['#attributes']['data-social-preview-control'];
      for (const [value, text] of Object.entries(element['#options'] ?? {})) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = text;
        control.appendChild(option);
      }
      control.value = element['#default_value'];
      wrapper.appendChild(control);
    }
    for (const [name, child] of Object.entries(element)) {
      if (!name.startsWith('#') && child && typeof child === 'object') addControls(child);
    }
  };
  addControls(definition.options);
  wrapper.insertAdjacentHTML('beforeend', definition.card['#markup']);
  return wrapper.outerHTML;
}

test('preview controls use Form API elements so the administration theme owns their styling', () => {
  const preview = previewDefinition();
  assert.equal(preview['#type'], 'container');
  assert.equal(preview.options['#type'], 'fieldset');
  assert.equal(preview.options['#title'], 'Preview options');
  const controls = [
    preview.options.selectors.emulsify_social_preview_platform,
    preview.options.selectors.emulsify_social_preview_device,
    preview.options.emulsify_social_preview_title,
    preview.options.emulsify_social_preview_description,
    preview.options.emulsify_social_preview_url,
  ];
  assert.deepEqual(controls.map(element => element['#type']), ['select', 'select', 'textfield', 'textarea', 'textfield']);
  for (const element of controls) assert(element['#title'], 'Every control needs a themed label');
  const { document } = new JSDOM(preview.card['#markup']).window;
  assert.equal(document.querySelectorAll('script, [onerror], strong, input, textarea, select').length, 0);
  assert.equal(document.querySelector('img').getAttribute('alt'), 'Artwork "alt" <script>bad()</script>');
  assert.equal(document.querySelector('img').getAttribute('width'), '1200');
  assert.equal(document.querySelector('[data-social-preview-title]').textContent, 'Site <strong>name</strong>');
});

test('an unconfigured image has a useful empty state without an empty image request', () => {
  const { document } = new JSDOM(previewMarkup('')).window;
  assert.equal(document.querySelector('img'), null);
  assert.match(document.querySelector('.emulsify-social-preview__empty').textContent, /Save an image path or upload an image/);
});

for (const relativePath of ['assets/admin/social-share-preview.js', 'dist/js/admin/social-share-preview.js']) {
  test(`${relativePath}: platform, device and text controls update safely without changing the saved image`, () => {
    const { window } = new JSDOM(previewMarkup(), { runScripts: 'outside-only' });
    const { document } = window;
    const attached = new WeakSet();
    window.Drupal = { behaviors: {} };
    window.once = (id, elements) => [...elements].filter((element) => {
      if (attached.has(element)) return false;
      attached.add(element);
      return true;
    });
    window.fetch = () => { throw new Error('The preview must not fetch remote metadata'); };
    window.eval(fs.readFileSync(path.join(root, relativePath), 'utf8'));
    const handlers = new WeakMap();
    window.jQuery = (element) => ({
      on(events, listener) {
        for (const event of events.split(' ')) {
          const name = event.split('.')[0];
          const registered = handlers.get(element) ?? {};
          registered[name] = listener;
          handlers.set(element, registered);
        }
      },
      trigger(event) {
        handlers.get(element)?.[event]?.();
      },
    });
    const attach = (context = document) => window.Drupal.behaviors.emulsifySocialSharePreview.attach(context);
    const change = (name, value) => {
      const control = document.querySelector(`[data-social-preview-control="${name}"]`);
      control.value = value;
      control.dispatchEvent(new window.Event('input'));
      control.dispatchEvent(new window.Event('change'));
    };
    const originalImage = document.querySelector('img').outerHTML;
    attach();
    for (const platform of ['x', 'linkedin', 'pinterest', 'facebook']) {
      change('platform', platform);
      assert.equal(document.querySelector('[data-social-preview-card]').dataset.platform, platform);
      assert.equal(document.querySelector('[data-social-preview-platform]').textContent, document.querySelector('[data-social-preview-control="platform"]').selectedOptions[0].textContent);
    }
    change('device', 'mobile');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'mobile');
    change('title', '<img src=x onerror=bad()>');
    change('description', '<script>bad()</script>');
    change('url', 'https://preview.example.test/a?b=1');
    assert.equal(document.querySelector('[data-social-preview-domain]').textContent, 'preview.example.test');
    change('url', '<script>bad()</script>');
    assert.equal(document.querySelector('[data-social-preview-domain]').textContent, '<script>bad()</script>');
    assert.equal(document.querySelectorAll('script, [onerror]').length, 0);
    assert.equal(document.querySelector('img').outerHTML, originalImage);
    const platform = document.querySelector('[data-social-preview-control="platform"]');
    platform.value = 'pinterest';
    window.jQuery(platform).trigger('change');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.platform, 'pinterest', 'Existing site enhancements may emit only a jQuery change event');
    const device = document.querySelector('[data-social-preview-control="device"]');
    device.value = 'desktop';
    window.jQuery(device).trigger('change');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'desktop');
    attach(document.querySelector('[data-social-share-preview]'));
    change('device', 'desktop');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'desktop');
    window.close();
  });
}
