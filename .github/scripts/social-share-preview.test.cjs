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
  echo $builder->build(
    ['social_share_image_alt' => 'Artwork "alt" <script>bad()</script>'],
    $argv[2],
    ['width' => 1200, 'height' => 630],
    'Site <strong>name</strong>',
    'Description & details',
    'https://example.test/page',
  )['#markup'];
}`;

function previewMarkup(imageUrl = 'https://example.test/saved.png?name="image"') {
  const result = spawnSync('php', ['-r', fixture, path.join(root, 'src/Social/SocialSharePreviewBuilder.php'), imageUrl], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return result.stdout;
}

test('preview controls stay local and user values cannot inject markup', () => {
  const { document } = new JSDOM(previewMarkup()).window;
  assert.equal(document.querySelectorAll('script, [onerror], strong').length, 0);
  assert.equal(document.querySelector('img').getAttribute('alt'), 'Artwork "alt" <script>bad()</script>');
  assert.equal(document.querySelector('img').getAttribute('width'), '1200');
  assert.equal(document.querySelector('[data-social-preview-title]').textContent, 'Site <strong>name</strong>');
  assert.equal(document.querySelectorAll('input[name], textarea[name], select[name]').length, 0, 'Mock metadata must never be saved with theme settings');
  for (const control of document.querySelectorAll('input, textarea, select')) {
    assert(control.closest('label'), 'Every native preview control needs a visible label');
  }
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
    window.once = (id, selector, context) => [...context.querySelectorAll(selector)].filter((element) => {
      if (attached.has(element)) return false;
      attached.add(element);
      return true;
    });
    window.fetch = () => { throw new Error('The preview must not fetch remote metadata'); };
    window.eval(fs.readFileSync(path.join(root, relativePath), 'utf8'));
    const attach = () => window.Drupal.behaviors.emulsifySocialSharePreview.attach(document);
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
    attach();
    change('device', 'desktop');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'desktop');
    window.close();
  });
}
