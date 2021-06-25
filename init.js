#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

const isObjectLiteral = (obj) =>
  obj != null && obj.constructor.name === 'Object';

const getEmulsifyConfig = () => {
  try {
    return require('./project.emulsify.json');
  } catch (e) {
    throw new Error(
      'Unable to find an Emulsify project configuration file (project.emulsify.json). Please create one, or re-initialize your project using the Emulsify CLI.',
    );
  }
};

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

const renameFiles = (files) =>
  files.map(({ from, to }) =>
    fs.rename(path.join(__dirname, from), path.join(__dirname, to)),
  );

const main = () => {
  // Load up config file, throw if none exists.
  const {
    project: { machineName, name },
  } = getEmulsifyConfig();

  // Validate config file, throw if it is missing
  //properties or is otherwise malformed.
  validateEmulsifyConfig(config);

  // Move all files to their correct location.
  renameFiles([
    {
      from: 'emulsify.info.yml',
      to: `${machineName}.info.yml`,
    },
    {
      from: 'emulsify.theme',
      to: `${machineName}.theme`,
    },
    {
      from: 'emulsify.breakpoints.yml',
      to: `${machineName}.breakpoints.yml`,
    },
    {
      from: 'emulsify.libraries.yml',
      to: `${machineName}.libraries.yml`,
    },
  ]);

  // Update info.yml file.
  const infoFilePath = path.join(__dirname, `./${machineName}.info.yml`);
  const info = yaml.safeLoad(fs.readFileSync(infoFilePath, 'utf8'));
  info.name = name;
  fs.writeFileSync(infoFilePath, yaml.safeDump(info));
};
