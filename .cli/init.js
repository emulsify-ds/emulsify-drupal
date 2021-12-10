#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

/**
 * Returns a boolean indicating whether or not the given object is a literal object.
 *
 * @param {any} obj object who's type will be checked.
 * @returns {boolean} boolean indicating whether or not the given obj is a literal object.
 */
const isObjectLiteral = (obj) =>
  obj != null && obj.constructor.name === 'Object';

/**
 * Attempts to require the project.emulsify.json file.
 *
 * @returns parsed project.emulsify.json file.
 */
const getEmulsifyConfig = () => {
  try {
    return require('../project.emulsify.json');
  } catch (e) {
    throw new Error(
      `Unable to load an Emulsify project config file (project.emulsify.json): ${String(
        e,
      )}`,
    );
  }
};

/**
 * Throws if the given emulsify config file is invalid.
 *
 * @param {*} config emulsify project config, as loaded from a project.emulsify.json file.
 */
const validateEmulsifyConfig = (config) => {
  const prefix = 'Invalid project.emulsify.json config file';
  const example = JSON.stringify({
    project: {
      name: 'Example Project',
      machineName: 'example-project',
    },
  });

  if (!config) {
    throw new Error(`${prefix}.`);
  }

  if (!config.project || !isObjectLiteral(config.project)) {
    throw new Error(
      `${prefix}: Must contain a "project" key, with a name and machineName property. ${example}`,
    );
  }

  if (typeof config.project.name !== 'string') {
    throw new Error(
      `${prefix}: the "project" object must contain a "name" key with a string value. ${example}`,
    );
  }

  if (typeof config.project.machineName !== 'string') {
    throw new Error(
      `${prefix}: the "project" object must contain a "machineName" key with a string value. ${example}`,
    );
  }
};

/**
 * Takes an array of objects describing the origin and destination of a given file,
 * then moves each specified file according to it's to/from properties.
 *
 * @param {Array<{ to: string, from: string }>} files array of objects depicting the origin and destination of a given file.
 * @returns void.
 */
const renameFiles = (files) =>
  files.map(({ from, to }) =>
    fs.renameSync(path.join(__dirname, from), path.join(__dirname, to)),
  );

/**
 * Takes a machineName, and returns a fn that, when called with a str,
 * replaces all instances of `emulsify` with the given machineName.
 *
 * @param {string} machineName string that should replace emulsify.
 * @returns {function} fn that when called with a str, replaces all instances of `emulsify` with the given machineName.
 */
const strReplaceEmulsify = (machineName) => (str) =>
  str.replace(/emulsify/g, machineName);

/**
 * Loads a yml file at filePath, applies the functor to the contents of the file, and writes it.
 *
 * @param {string} filePath path to the file that should be loaded, modified, and re-saved.
 * @param {fn} functor fn that should return the new contents of the file, to be saved.
 * @returns void.
 */
const applyToYmlFile = (filePath, functor) => {
  if (!filePath || typeof filePath !== `string`) {
    throw new Error(
      `Cannot modify a file without knowing how to access it: ${filePath}`,
    );
  }
  if (typeof functor !== 'function') {
    return;
  }

  const file = yaml.safeLoad(fs.readFileSync(filePath, 'utf8'));
  fs.writeFileSync(filePath, yaml.safeDump(functor(file)));
};

const main = () => {
  // Load up config file, throw if none exists.
  const config = getEmulsifyConfig();

  // Validate config file, throw if it is missing
  //properties or is otherwise malformed.
  validateEmulsifyConfig(config);

  const {
    project: { machineName, name },
  } = config;

  // Move all files to their correct location.
  renameFiles([
    {
      from: '../emulsify.info.yml',
      to: `../${machineName}.info.yml`,
    },
    {
      from: '../emulsify.theme',
      to: `../${machineName}.theme`,
    },
    {
      from: '../emulsify.breakpoints.yml',
      to: `../${machineName}.breakpoints.yml`,
    },
    {
      from: '../emulsify.libraries.yml',
      to: `../${machineName}.libraries.yml`,
    },
  ]);

  // Update info.yml file.
  applyToYmlFile(
    path.join(__dirname, `../${machineName}.info.yml`),
    (info) => ({
      ...info,
      name: machineName,
      libraries: info.libraries.map(strReplaceEmulsify(machineName)),
    }),
  );

  // Update breakpoint.yml file.
  applyToYmlFile(
    path.join(__dirname, `../${machineName}.breakpoints.yml`),
    (breakpoints) => {
      const newBps = {};
      for (const prop of Object.keys(breakpoints)) {
        newBps[strReplaceEmulsify(machineName)(prop)] = breakpoints[prop];
      }
      return newBps;
    },
  );
};

main();
