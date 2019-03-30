<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Plugin;

use App\Constants;

class PluginManager
{
    /**
     * @var Plugin[]
     */
    private $plugins = [];

    /**
     * @param PluginInterface $plugin
     * @throws \Exception
     */
    public function addPlugin(PluginInterface $plugin)
    {
        if (isset($this->plugins[$plugin->getName()])) {
            return;
        }

        $this->plugins[$plugin->getName()] = $this->createPlugin($plugin);
    }

    /**
     * @return Plugin[]
     */
    public function getPlugins()
    {
        return $this->plugins;
    }

    /**
     * @param string $name
     * @return Plugin|null
     */
    public function getPlugin(string $name): ?Plugin
    {
        if (!isset($this->plugins[$name])) {
            return null;
        }

        return $this->plugins[$name];
    }

    /**
     * @param PluginInterface $bundle
     * @return Plugin
     */
    protected function createPlugin(PluginInterface $bundle)
    {
        $plugin = new Plugin();
        $plugin
            ->setName($bundle->getName())
            ->setPath($bundle->getPath())
        ;

        return $plugin;
    }

    /**
     * Call this method and pass a plugin, to set its metdata.
     * This is not pre-filled by default, as it would mean to parse several composer.json on each request.
     *
     * @param Plugin $plugin
     */
    public function loadMetadata(Plugin $plugin)
    {
        $composer = $plugin->getPath() . '/composer.json';
        if (!file_exists($composer) || !is_readable($composer)) {
            return null;
        }

        $json = json_decode(file_get_contents($composer), true);

        $reqVersion = isset($json['extra']['kimai']['require']) ? $json['extra']['kimai']['require'] : 'unknown';
        $version = isset($json['extra']['kimai']['version']) ? $json['extra']['kimai']['version'] : 'unknown';
        $description = isset($json['description']) ? $json['description'] : '';

        $homepage = $json['homepage'] ?? Constants::HOMEPAGE . '/store/';
        $metadata = new PluginMetadata();
        $metadata
            ->setHomepage($homepage)
            ->setKimaiVersion($reqVersion)
            ->setVersion($version)
            ->setDescription($description)
        ;

        $plugin->setMetadata($metadata);
    }
}
