import { resolve } from 'path';
import { readFileSync } from 'fs';
import { parse } from 'yaml';
import R from 'ramda';

/**
 * Small utility function that loads a yaml file and parses it synchronously.
 * This is intended to make composition cleaner.
 *
 * @param {string} relativePath - relative path to a yaml file that will be loaded and parsed.
 *
 * @returns {string} JavaScript object that results from the yaml parsing of the specified file.
 */
export default function loadYaml(relativePath) {
  const fullPath = resolve(__dirname, relativePath);
  return parse(readFileSync(fullPath, 'utf8'));
}
