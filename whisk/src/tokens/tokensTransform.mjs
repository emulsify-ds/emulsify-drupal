import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const tokenDirectory = scriptDirectory;
const sourcePath = path.join(tokenDirectory, 'figma.tokens.json');
const jsonOutputPath = path.join(tokenDirectory, 'transformed.tokens.json');
const scssOutputPath = path.join(tokenDirectory, '_generated.scss');
const args = new Set(process.argv.slice(2));
const writeJson = args.size === 0 || args.has('--json');
const writeScss = args.size === 0 || args.has('--scss');

function readTokenSource() {
  try {
    return JSON.parse(fs.readFileSync(sourcePath, 'utf8'));
  }
  catch (error) {
    throw new Error(`Unable to read design tokens from ${sourcePath}: ${error.message}`);
  }
}

function normalizeSegment(segment) {
  return segment
    .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
    .replace(/[^A-Za-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .toLowerCase();
}

function tokenNameFromPath(segments) {
  const usefulSegments = segments[0] === 'global' ? segments.slice(1) : segments;
  return usefulSegments.map(normalizeSegment).filter(Boolean).join('-');
}

function flattenTokens(value, segments = [], tokens = []) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return tokens;
  }

  const tokenValue = value.$value ?? value.value;
  if (tokenValue !== undefined) {
    const name = tokenNameFromPath(segments);
    if (name) {
      tokens.push({
        name,
        value: String(tokenValue),
        type: value.$type ?? value.type ?? null,
      });
    }
    return tokens;
  }

  for (const [key, child] of Object.entries(value)) {
    flattenTokens(child, [...segments, key], tokens);
  }

  return tokens;
}

function renderTransformedJson(tokens) {
  const transformed = Object.fromEntries(
    tokens.map((token) => [
      token.name,
      {
        value: token.value,
        type: token.type,
      },
    ]),
  );

  return `${JSON.stringify(transformed, null, 2)}\n`;
}

function renderScss(tokens) {
  const declarations = tokens
    .map((token) => `  --emulsify-${token.name}: ${token.value};`)
    .join('\n');

  return `:root {\n${declarations}\n}\n`;
}

const tokens = flattenTokens(readTokenSource());

if (tokens.length === 0) {
  throw new Error(`No tokens were found in ${sourcePath}.`);
}

if (writeJson) {
  fs.writeFileSync(jsonOutputPath, renderTransformedJson(tokens));
  console.log(`Wrote ${path.relative(process.cwd(), jsonOutputPath)}`);
}

if (writeScss) {
  fs.writeFileSync(scssOutputPath, renderScss(tokens));
  console.log(`Wrote ${path.relative(process.cwd(), scssOutputPath)}`);
}
