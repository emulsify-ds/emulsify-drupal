import { readFileSync } from 'node:fs';
import Twig from 'twig';

const template = Twig.twig({
  data: readFileSync(new URL('./emulsify-smoke.twig', import.meta.url), 'utf8'),
  autoescape: true,
});

afterEach(() => document.body.replaceChildren());

test('renders the project status panel and its documentation link', () => {
  document.body.innerHTML = template.render({
    title: 'Your component library is ready',
    description: 'Project-owned component content.',
    url: 'https://www.emulsify.info',
    link_text: 'Read the documentation',
  });

  expect(document.querySelector('main h1').textContent).toBe('Your component library is ready');
  expect(document.querySelector('main p').textContent).toBe('Project-owned component content.');
  expect(document.querySelector('main a').getAttribute('href')).toBe('https://www.emulsify.info');
  expect(document.querySelector('main a').textContent).toBe('Read the documentation');
});

test('renders user-provided text as text rather than HTML', () => {
  const title = '<img src="x" onerror="alert(1)">';
  document.body.innerHTML = template.render({
    title,
    description: 'Membership & support',
    url: '/support',
    link_text: 'Learn <more>',
  });

  expect(document.querySelector('h1').textContent).toBe(title);
  expect(document.querySelector('p').textContent).toBe('Membership & support');
  expect(document.querySelector('a').textContent).toBe('Learn <more>');
  expect(document.querySelector('img')).toBeNull();
});
