<?php
/**
 * ________
 * ___  __ \____  ______________
 * __  / / /_  / / /_  __ \  __ \
 * _  /_/ /_  /_/ /_  / / / /_/ /
 * /_____/ _\__, / /_/ /_/\____/
 *         /____/
 *
 * This program is free: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is based on PocketMine Software and Synapse.
 *
 * @copyright (c) 2020
 * @author Y&SS-YassLV
 */

namespace dyno\plugin;

use dyno\command\PluginCommand;
use dyno\command\SimpleCommandMap;
use dyno\event\Event;
use dyno\event\EventPriority;
use dyno\event\HandlerList;
use dyno\event\Listener;
use dyno\exception\PluginException;
use dyno\Server;

/**
 * Manages all the plugins
 */
class PluginManager
{

    public static $pluginParentTimer;
    /**
     * @var Plugin[]
     */
    protected $plugins = [];

    /**
     * @var PluginLoader[]
     */
    protected $fileAssociations = [];
    /** @var Server */
    private $server;
    /** @var SimpleCommandMap */
    private $commandMap;

    /**
     * @param Server $server
     * @param SimpleCommandMap $commandMap
     */
    public function __construct(Server $server, SimpleCommandMap $commandMap)
    {
        $this->server = $server;
        $this->commandMap = $commandMap;
    }

    /**
     * @param string $loaderName A PluginLoader class name
     *
     * @return boolean
     */
    public function registerInterface($loaderName)
    {
        if (is_subclass_of($loaderName, PluginLoader::class)) {
            $loader = new $loaderName($this->server);
        } else {
            return false;
        }

        $this->fileAssociations[$loaderName] = $loader;

        return true;
    }

    /**
     * @param string $directory
     * @param array $newLoaders
     *
     * @return Plugin[]
     */
    public function loadPlugins($directory, $newLoaders = null)
    {

        if (is_dir($directory)) {
            $plugins = [];
            $loadedPlugins = [];
            $dependencies = [];
            $softDependencies = [];
            if (is_array($newLoaders)) {
                $loaders = [];
                foreach ($newLoaders as $key) {
                    if (isset($this->fileAssociations[$key])) {
                        $loaders[$key] = $this->fileAssociations[$key];
                    }
                }
            } else {
                $loaders = $this->fileAssociations;
            }
            foreach ($loaders as $loader) {
                foreach (new \RegexIterator(new \DirectoryIterator($directory), $loader->getPluginFilters()) as $file) {
                    if ($file === "." or $file === "..") {
                        continue;
                    }
                    $file = $directory . $file;
                    try {
                        $description = $loader->getPluginDescription($file);
                        if ($description instanceof PluginDescription) {
                            $name = $description->getName();
                            if (stripos($name, "dyno") !== false or stripos($name, "minecraft") !== false or stripos($name, "mojang") !== false) {
                                $this->server->getLogger()->error($this->server->getLanguage()->translateString("dyno.plugin.loadError", [$name, "%dyno.plugin.restrictedName"]));
                                continue;
                            } elseif (strpos($name, " ") !== false) {
                                $this->server->getLogger()->warning($this->server->getLanguage()->translateString("dyno.plugin.spacesDiscouraged", [$name]));
                            }

                            if (isset($plugins[$name]) or $this->getPlugin($name) instanceof Plugin) {
                                $this->server->getLogger()->error($this->server->getLanguage()->translateString("dyno.plugin.duplicateError", [$name]));
                                continue;
                            }

                            $compatible = false;
                            //Check multiple dependencies
                            foreach ($description->getCompatibleApis() as $version) {
                                //Format: majorVersion.minorVersion.patch
                                $version = array_map("intval", explode(".", $version));
                                $apiVersion = array_map("intval", explode(".", $this->server->getApiVersion()));
                                //Completely different API version
                                if ($version[0] > $apiVersion[0]) {
                                    continue;
                                }
                                //If the plugin uses new API
                                if ($version[0] < $apiVersion[0]) {
                                    $compatible = true;
                                    break;
                                }
                                //If the plugin requires new API features, being backwards compatible
                                if ($version[1] > $apiVersion[1]) {
                                    continue;
                                }

                                $compatible = true;
                                break;
                            }

                            if ($compatible === false) {
                                $this->server->getLogger()->error($this->server->getLanguage()->translateString("dyno.plugin.loadError", [$name, "%dyno.plugin.incompatibleAPI"]));
                                continue;
                            }

                            $plugins[$name] = $file;

                            $softDependencies[$name] = (array)$description->getSoftDepend();
                            $dependencies[$name] = (array)$description->getDepend();

                            foreach ($description->getLoadBefore() as $before) {
                                if (isset($softDependencies[$before])) {
                                    $softDependencies[$before][] = $name;
                                } else {
                                    $softDependencies[$before] = [$name];
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->server->getLogger()->error($this->server->getLanguage()->translateString("dyno.plugin.fileError", [$file, $directory, $e->getMessage()]));
                        $this->server->getLogger()->logException($e);
                    }
                }
            }


            while (count($plugins) > 0) {
                $missingDependency = true;
                foreach ($plugins as $name => $file) {
                    if (isset($dependencies[$name])) {
                        foreach ($dependencies[$name] as $key => $dependency) {
                            if (isset($loadedPlugins[$dependency]) or $this->getPlugin($dependency) instanceof Plugin) {
                                unset($dependencies[$name][$key]);
                            } elseif (!isset($plugins[$dependency])) {
                                $this->server->getLogger()->critical($this->server->getLanguage()->translateString("dyno.plugin.loadError", [$name, "%dyno.plugin.unknownDependency"]));
                                break;
                            }
                        }

                        if (count($dependencies[$name]) === 0) {
                            unset($dependencies[$name]);
                        }
                    }

                    if (isset($softDependencies[$name])) {
                        foreach ($softDependencies[$name] as $key => $dependency) {
                            if (isset($loadedPlugins[$dependency]) or $this->getPlugin($dependency) instanceof Plugin) {
                                unset($softDependencies[$name][$key]);
                            }
                        }

                        if (count($softDependencies[$name]) === 0) {
                            unset($softDependencies[$name]);
                        }
                    }

                    if (!isset($dependencies[$name]) and !isset($softDependencies[$name])) {
                        unset($plugins[$name]);
                        $missingDependency = false;
                        if ($plugin = $this->loadPlugin($file, $loaders) and $plugin instanceof Plugin) {
                            $loadedPlugins[$name] = $plugin;
                        } else {
                            $this->server->getLogger()->critical($this->server->getLanguage()->translateString("dyno.plugin.genericLoadError", [$name]));
                        }
                    }
                }

                if ($missingDependency === true) {
                    foreach ($plugins as $name => $file) {
                        if (!isset($dependencies[$name])) {
                            unset($softDependencies[$name]);
                            unset($plugins[$name]);
                            $missingDependency = false;
                            if ($plugin = $this->loadPlugin($file, $loaders) and $plugin instanceof Plugin) {
                                $loadedPlugins[$name] = $plugin;
                            } else {
                                $this->server->getLogger()->critical($this->server->getLanguage()->translateString("dyno.plugin.genericLoadError", [$name]));
                            }
                        }
                    }

                    //No plugins loaded :(
                    if ($missingDependency === true) {
                        foreach ($plugins as $name => $file) {
                            $this->server->getLogger()->critical($this->server->getLanguage()->translateString("dyno.plugin.loadError", [$name, "%dyno.plugin.circularDependency"]));
                        }
                        $plugins = [];
                    }
                }
            }


            return $loadedPlugins;
        } else {

            return [];
        }
    }

    /**
     * @param string $name
     *
     * @return null|Plugin
     */
    public function getPlugin($name)
    {
        if (isset($this->plugins[$name])) {
            return $this->plugins[$name];
        }

        return null;
    }

    /**
     * @param string $path
     * @param PluginLoader[] $loaders
     *
     * @return Plugin
     */
    public function loadPlugin($path, $loaders = null)
    {
        foreach (($loaders === null ? $this->fileAssociations : $loaders) as $loader) {
            if (preg_match($loader->getPluginFilters(), basename($path)) > 0) {
                $description = $loader->getPluginDescription($path);
                if ($description instanceof PluginDescription) {
                    if (($plugin = $loader->loadPlugin($path)) instanceof Plugin) {
                        $this->plugins[$plugin->getDescription()->getName()] = $plugin;

                        $pluginCommands = $this->parseYamlCommands($plugin);

                        if (count($pluginCommands) > 0) {
                            $this->commandMap->registerAll($plugin->getDescription()->getName(), $pluginCommands);
                        }

                        return $plugin;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param Plugin $plugin
     *
     * @return PluginCommand[]
     */
    protected function parseYamlCommands(Plugin $plugin)
    {
        $pluginCmds = [];

        foreach ($plugin->getDescription()->getCommands() as $key => $data) {
            if (strpos($key, ":") !== false) {
                $this->server->getLogger()->critical($this->server->getLanguage()->translateString("dyno.plugin.commandError", [$key, $plugin->getDescription()->getFullName()]));
                continue;
            }
            if (is_array($data)) {
                $newCmd = new PluginCommand($key, $plugin);
                if (isset($data["description"])) {
                    $newCmd->setDescription($data["description"]);
                }

                if (isset($data["usage"])) {
                    $newCmd->setUsage($data["usage"]);
                }

                if (isset($data["aliases"]) and is_array($data["aliases"])) {
                    $aliasList = [];
                    foreach ($data["aliases"] as $alias) {
                        if (strpos($alias, ":") !== false) {
                            $this->server->getLogger()->critical($this->server->getLanguage()->translateString("dyno.plugin.aliasError", [$alias, $plugin->getDescription()->getFullName()]));
                            continue;
                        }
                        $aliasList[] = $alias;
                    }

                    $newCmd->setAliases($aliasList);
                }

                $pluginCmds[] = $newCmd;
            }
        }

        return $pluginCmds;
    }

    /**
     * @param Plugin $plugin
     *
     * @return bool
     */
    public function isPluginEnabled(Plugin $plugin)
    {
        if ($plugin instanceof Plugin and isset($this->plugins[$plugin->getDescription()->getName()])) {
            return $plugin->isEnabled();
        } else {
            return false;
        }
    }

    /**
     * @param Plugin $plugin
     */
    public function enablePlugin(Plugin $plugin)
    {
        if (!$plugin->isEnabled()) {
            try {
                $plugin->getPluginLoader()->enablePlugin($plugin);
            } catch (\Throwable $e) {
                $this->server->getLogger()->logException($e);
                $this->disablePlugin($plugin);
            }
        }
    }

    /**
     * @param Plugin $plugin
     */
    public function disablePlugin(Plugin $plugin)
    {
        if ($plugin->isEnabled()) {
            try {
                $plugin->getPluginLoader()->disablePlugin($plugin);
            } catch (\Throwable $e) {
                $this->server->getLogger()->logException($e);
            }

            $this->server->getScheduler()->cancelTasks($plugin);
            HandlerList::unregisterAll($plugin);
        }
    }

    public function clearPlugins()
    {
        $this->disablePlugins();
        $this->plugins = [];
        $this->fileAssociations = [];
    }

    public function disablePlugins()
    {
        foreach ($this->getPlugins() as $plugin) {
            $this->disablePlugin($plugin);
        }
    }

    /**
     * @return Plugin[]
     */
    public function getPlugins()
    {
        return $this->plugins;
    }

    /**
     * Calls an event
     *
     * @param Event $event
     */
    public function callEvent(Event $event)
    {
        foreach ($event->getHandlers()->getRegisteredListeners() as $registration) {
            if (!$registration->getPlugin()->isEnabled()) {
                continue;
            }

            try {
                $registration->callEvent($event);
            } catch (\Throwable $e) {
                $this->server->getLogger()->critical(
                    $this->server->getLanguage()->translateString("dyno.plugin.eventError", [
                        $event->getEventName(),
                        $registration->getPlugin()->getDescription()->getFullName(),
                        $e->getMessage(),
                        get_class($registration->getListener())
                    ]));
                $this->server->getLogger()->logException($e);
            }
        }
    }

    /**
     * Registers all the events in the given Listener class
     *
     * @param Listener $listener
     * @param Plugin $plugin
     *
     * @throws \ReflectionException
     */
    public function registerEvents(Listener $listener, Plugin $plugin)
    {
        if (!$plugin->isEnabled()) {
            throw new PluginException("Plugin attempted to register " . get_class($listener) . " while not enabled");
        }

        $reflection = new \ReflectionClass(get_class($listener));
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic()) {
                $priority = EventPriority::NORMAL;
                $ignoreCancelled = false;
                if (preg_match("/^[\t ]*\\* @priority[\t ]{1,}([a-zA-Z]{1,})/m", (string)$method->getDocComment(), $matches) > 0) {
                    $matches[1] = strtoupper($matches[1]);
                    if (defined(EventPriority::class . "::" . $matches[1])) {
                        $priority = constant(EventPriority::class . "::" . $matches[1]);
                    }
                }
                if (preg_match("/^[\t ]*\\* @ignoreCancelled[\t ]{1,}([a-zA-Z]{1,})/m", (string)$method->getDocComment(), $matches) > 0) {
                    $matches[1] = strtolower($matches[1]);
                    if ($matches[1] === "false") {
                        $ignoreCancelled = false;
                    } elseif ($matches[1] === "true") {
                        $ignoreCancelled = true;
                    }
                }

                $parameters = $method->getParameters();
                if (count($parameters) === 1 and $parameters[0]->getClass() instanceof \ReflectionClass and is_subclass_of($parameters[0]->getClass()->getName(), Event::class)) {
                    $class = $parameters[0]->getClass()->getName();
                    $reflection = new \ReflectionClass($class);
                    if (strpos((string)$reflection->getDocComment(), "@deprecated") !== false and $this->server->getProperty("settings.deprecated-verbose", true)) {
                        $this->server->getLogger()->warning($this->server->getLanguage()->translateString("dyno.plugin.deprecatedEvent", [
                            $plugin->getName(),
                            $class,
                            get_class($listener) . "->" . $method->getName() . "()"
                        ]));
                    }
                    $this->registerEvent($class, $listener, $priority, new MethodEventExecutor($method->getName()), $plugin, $ignoreCancelled);
                }
            }
        }
    }

    /**
     * @param string $event Class name that extends Event
     * @param Listener $listener
     * @param int $priority
     * @param EventExecutor $executor
     * @param Plugin $plugin
     * @param bool $ignoreCancelled
     *
     * @throws \ReflectionException
     */
    public function registerEvent($event, Listener $listener, $priority, EventExecutor $executor, Plugin $plugin, $ignoreCancelled = false)
    {
        if (!is_subclass_of($event, Event::class)) {
            throw new PluginException($event . " is not an Event");
        }
        $class = new \ReflectionClass($event);
        if ($class->isAbstract()) {
            throw new PluginException($event . " is an abstract Event");
        }
        if ($class->getProperty("handlerList")->getDeclaringClass()->getName() !== $event) {
            throw new PluginException($event . " does not have a handler list");
        }

        if (!$plugin->isEnabled()) {
            throw new PluginException("Plugin attempted to register " . $event . " while not enabled");
        }


        $this->getEventListeners($event)->register(new RegisteredListener($listener, $executor, $priority, $plugin, $ignoreCancelled));
    }

    /**
     * @param $event
     *
     * @return HandlerList
     */
    private function getEventListeners($event)
    {
        if ($event::$handlerList === null) {
            $event::$handlerList = new HandlerList();
        }

        return $event::$handlerList;
    }

}
