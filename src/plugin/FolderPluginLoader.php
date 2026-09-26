<?php
declare(strict_types=1);

namespace pocketmine\plugin;

use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\Plugin;
use pocketmine\thread\ThreadSafeClassLoader;
use function is_dir;
use function file_exists;
use function file_get_contents;

class FolderPluginLoader implements PluginLoader {

	/**
	 * @phpstan-var ThreadSafeClassLoader
	 */
	private ThreadSafeClassLoader $loader;

	/**
	 * @param ThreadSafeClassLoader $loader
	 */
	public function __construct(ThreadSafeClassLoader $loader) {
		$this->loader = $loader;
	}

	/**
	 * @return ThreadSafeClassLoader
	 */
	public function getLoader() : ThreadSafeClassLoader {
		return $this->loader;
	}

	/**
	 * {@inheritDoc}
	 */
	public function canLoadPlugin(string $path) : bool{
		return is_dir($path) && file_exists($path . "/plugin.yml") && is_dir($path . "/src");
	}

	/**
	 * {@inheritDoc}
	 * @throws \PluginDescriptionParseException
	 */
	public function loadPlugin(string $file) : void{
		$description = $this->getPluginDescription($file);
		if($description !== null){
			$this->loader->addPath($description->getSrcNamespacePrefix(), "$file/src");
		}
	}

	/**
	 * {@inheritDoc}
	 * @throws \PluginDescriptionParseException
	 */
	public function getPluginDescription(string $file) : ?PluginDescription{
		if(is_dir($file) && file_exists($file . "/plugin.yml")){
			$yaml = @file_get_contents($file . "/plugin.yml");
			if($yaml !== ""){
				return new PluginDescription($yaml);
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAccessProtocol() : string{
		return "";
	}
}