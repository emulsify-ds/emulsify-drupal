<?php

declare(strict_types = 1);

namespace Drupal\emulsify\Commands;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The emulsify subtheme generator.
 */
class SubThemeGenerator {

  /**
   * The filesystem.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * The finder.
   *
   * @var \Symfony\Component\Finder\Finder
   */
  protected $finder;

  /**
   * The old machine name.
   *
   * @var string
   */
  protected $machineNameOld = '';

  /**
   * The directory.
   *
   * @var string
   */
  protected $dir = '';

  /**
   * The machine name.
   *
   * @var string
   */
  protected $machineName = '';

  /**
   * The name.
   *
   * @var string
   */
  protected $name = '';

  /**
   * The description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * Initialize the class.
   */
  public function __construct() {
    $this->fs = new Filesystem();
    $this->finder = new Finder();
  }

  /**
   * Get the directory.
   *
   * @return string
   *   The directory.
   */
  public function getDir(): string {
    return $this->dir;
  }

  /**
   * Set the directory.
   *
   * @param string $dir
   *   Directory where a Emulsify starter recipe already copied to.
   *
   * @return $this
   */
  public function setDir(string $dir) {
    $this->dir = $dir;

    return $this;
  }

  /**
   * Get the machine name.
   *
   * @return string
   *   The machine name.
   */
  public function getMachineName(): string {
    if (!$this->machineName) {
      return basename($this->getDir());
    }

    return $this->machineName;
  }

  /**
   * Set the machine name.
   *
   * @param string $machineName
   *   The machine name.
   *
   * @return $this
   */
  public function setMachineName(string $machineName) {
    $this->machineName = $machineName;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Set name.
   *
   * @param string $name
   *   The name.
   *
   * @return $this
   */
  public function setName(string $name) {
    $this->name = $name;

    return $this;
  }

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Set the description.
   *
   * @param string $description
   *   The description.
   *
   * @return $this
   */
  public function setDescription(string $description) {
    $this->description = $description;

    return $this;
  }

  /**
   * Do generation.
   *
   * @return $this
   */
  public function generate() {
    return $this
      ->initMachineNameOld()
      ->modifyFileContents()
      ->renameFiles();
  }

  /**
   * Initialize the old machine name.
   *
   * @return $this
   */
  protected function initMachineNameOld() {
    $dstDir = $this->getDir();
    $infoFiles = glob("$dstDir/*.info.emulsify.yml");

    $this->machineNameOld = basename(reset($infoFiles), '.info.emulsify.yml');

    return $this;
  }

  /**
   * Modify file contents.
   *
   * @return $this
   */
  protected function modifyFileContents() {
    $replacementPairs = $this->getFileContentReplacementPairs();
    foreach ($this->getFilesToMakeReplacements() as $fileName) {
      $this->modifyFileContent($fileName, $replacementPairs);
    }

    return $this;
  }

  /**
   * Rename files.
   *
   * @return $this
   */
  protected function renameFiles() {
    $machineNameNew = $this->getMachineName();
    if ($this->machineNameOld === $machineNameNew) {
      return $this;
    }

    foreach ($this->getFileNamesToRename() as $fileName) {
      $newFileName = str_replace($this->machineNameOld, $machineNameNew, $fileName);
      if (strpos($newFileName, '.emulsify.') !== FALSE) {
        $newFileName = str_replace('.emulsify.', '.', $newFileName);
      }
      $this->fs->rename($fileName, $newFileName);
    }

    return $this;
  }

  /**
   * Modify file contents.
   *
   * @return $this
   */
  protected function modifyFileContent(string $fileName, array $replacementPairs) {
    if (!$this->fs->exists($fileName)) {
      return $this;
    }

    $this->fs->dumpFile(
      $fileName,
      strtr($this->fileGetContents($fileName), $replacementPairs)
    );

    return $this;
  }

  /**
   * Get file names to rename.
   *
   * @return string[]
   *   An array of file names.
   */
  protected function getFileNamesToRename(): array {
    // Find all files within the theme that match *{RECIPE_NAME}*.
    return array_keys(iterator_to_array($this->finder->files()->name("*{$this->machineNameOld}*")->in($this->getDir())));
  }

  /**
   * Get file content replacement pairs.
   *
   * @return string[]
   *   The replacement pairs.
   */
  protected function getFileContentReplacementPairs(): array {
    return [
      'EMULSIFY_RECIPE_NAME' => $this->getName(),
      'EMULSIFY_RECIPE_DESCRIPTION' => $this->getDescription(),
      'emulsify_recipe' => $this->getMachineName(),
      "\nhidden: true\n" => "\n",
    ];
  }

  /**
   * Get files to make replacements.
   *
   * @return string[]
   *   An array of files to me replacements on.
   */
  public function getFilesToMakeReplacements(): array {
    return array_keys(iterator_to_array($this->finder->files()->in($this->getDir())));
  }

  /**
   * Get file contents.
   *
   * @return string
   *   The file contents.
   */
  protected function fileGetContents(string $fileName): string {
    $content = file_get_contents($fileName);
    if ($content === FALSE) {
      throw new \RuntimeException("Could not read file '$fileName'", 1);
    }

    return $content;
  }

}
