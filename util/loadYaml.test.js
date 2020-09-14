import fs from 'fs';
import yaml from 'yaml';
import loadYaml from './loadYaml';

jest
  .spyOn(fs, 'readFileSync')
  .mockImplementation(() => 'yaml spaghetti and meatballs');

jest.spyOn(yaml, 'parse').mockImplementation(() => ({
  the: 'yaml spaghetti and meatballs',
}));

describe('loadYaml', () => {
  beforeEach(() => {
    fs.readFileSync.mockClear();
    yaml.parse.mockClear();
  });

  it('can load a yaml file, parse it, and return it', () => {
    expect.assertions(3);
    expect(loadYaml('./big-phat-burger.yml')).toEqual({
      the: 'yaml spaghetti and meatballs',
    });
    expect(fs.readFileSync).toHaveBeenCalledWith(
      `${__dirname}/big-phat-burger.yml`,
      'utf8',
    );
    expect(yaml.parse).toHaveBeenCalledWith('yaml spaghetti and meatballs');
  });
});
