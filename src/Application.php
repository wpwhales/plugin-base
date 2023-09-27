<?php

namespace WPWhales\Plugin;

use WPWhales\Config\Repository;
use WPWhales\Container\Container;
use WPWhales\Events\EventServiceProvider;
use WPWhales\Filesystem\Filesystem;
use WPWhales\Filesystem\FilesystemServiceProvider;
use WPWhales\Support\ServiceProvider;
use WPWhales\View\ViewServiceProvider;


class Application extends Container
{


    /**
     * App termination status
     * useful to watch in wordpress shutdown hook to terminate the application
     * when wordpress is end as well
     * @var boolean
     */
    private $terminated = false;

    /**
     * @var string
     */
    public $packagePath;
    /**
     * The custom bootstrap path defined by the developer.
     *
     * @var string
     */
    protected $bootstrapPath;

    /**
     * Indicates if the class aliases have been registered.
     *
     * @var bool
     */
    protected static $aliasesRegistered = false;

    /**
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * All of the loaded configuration files.
     *
     * @var array
     */
    protected $loadedConfigurations = [];

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * The service binding methods that have been executed.
     *
     * @var array
     */
    protected $ranServiceBinders = [];

    /**
     * The custom application path defined by the developer.
     *
     * @var string
     */
    protected $appPath;

    /**
     * The custom configuration path defined by the developer.
     *
     * @var string
     */
    protected $configPath;

    /**
     * The custom database path defined by the developer.
     *
     * @var string
     */
    protected $databasePath;

    /**
     * The custom language file path defined by the developer.
     *
     * @var string
     */
    protected $langPath;

    /**
     * The custom public / web path defined by the developer.
     *
     * @var string
     */
    protected $publicPath;

    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected $storagePath;

    /**
     * The custom environment path defined by the developer.
     *
     * @var string
     */
    protected $environmentPath;

    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected $environmentFile = '.env';

    /**
     * The application namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The Router instance.
     *
     * @var \Laravel\Lumen\Routing\Router
     */
    public $router;

    /**
     * The Ajax Router instance.
     *
     * @var \Laravel\Lumen\Routing\AjaxRouter
     */
    public $ajax_router;

    /**
     * The Admin Menu Pages Router instance.
     *
     * @var \Laravel\Lumen\Routing\AdminRouter
     */
    public $adminRouter;

    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    protected $terminatingCallbacks = [];

    protected $lazyConfigs = [];
    /*
    *
    * List of base providers to automatically bind with Lumen app
    */
    protected $baseProviders = [
    ];


    /**
     * The available container bindings and their respective load methods.
     *
     * @var array
     */
    public $availableBindings = [
        'config' => 'registerConfigBindings',
    ];


    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->bootstrapContainer();
        $this->bootstrapProviders();
        $this->loadBaseConfigFiles();
    }


    /**
     * Load a configuration file into the application.
     *
     * @param string $name
     * @return void
     */
    public function configure($name)
    {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }

        $this->loadedConfigurations[$name] = true;

        $path = $this->getConfigurationPath($name);

        if ($path) {
            $this->make('config')->set($name, require $path);
        }
    }

    public function lazyConfigure($name,$path)
    {

        $this->lazyConfigs[$name]=$path;
    }

    /**
     * Get the path to the given configuration file.
     *
     * If no name is provided, then we'll return the path to the config folder.
     *
     * @param string|null $name
     * @return string
     */
    public function getConfigurationPath($name = null)
    {


        if (!$name) {

            $appConfigDir = $this->basePath('config') . '/';

            if (file_exists($appConfigDir)) {
                return $appConfigDir;
            } elseif (file_exists($path = __DIR__ . '/../config/')) {
                return $path;
            }

        } else {

            $appConfigPath = $this->basePath('config') . '/' . $name . '.php';

            if (file_exists($appConfigPath)) {
                return $appConfigPath;
            } elseif (file_exists($path = __DIR__ . '/../config/' . $name . '.php')) {
                return $path;
            }elseif(isset($this->lazyConfigs[$name]) && file_exists($path = $this->lazyConfigs[$name] . '/config/' . $name . '.php')){
                return $path;
            }
        }
    }

    /**
     * Get the path to the resources directory.
     *
     * @param string|null $path
     * @return string
     */
    public function resourcePath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    protected function bootstrapProviders()
    {

        foreach ($this->baseProviders as $provider) {
            $this->register($provider);
        }


    }

    /**
     * Register a service provider with the application.
     *
     * @param \WPWhales\Support\ServiceProvider|string $provider
     * @return void
     */
    public function register($provider)
    {
        if (!$provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        if (array_key_exists($providerName = get_class($provider), $this->loadedProviders)) {
            return;
        }

        $this->loadedProviders[$providerName] = $provider;

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        if ($this->booted) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param callable|string $callback
     * @return $this
     */
    public function terminating($callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Boot the given service provider.
     *
     * @param \WPWhales\Support\ServiceProvider $provider
     * @return mixed
     */
    protected function bootProvider(ServiceProvider $provider)
    {
        if (method_exists($provider, 'boot')) {
            return $this->call([$provider, 'boot']);
        }
    }

    /**
     * Bootstrap the application container.
     *
     * @return void
     */
    protected function bootstrapContainer()
    {

        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance('path', $this->path());
        $this->registerContainerAliases();

    }


    /**
     * Register the core container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases()
    {
        $this->aliases = [


        ];
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @return string
     */
    public function path()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app';
    }


    protected function loadBaseConfigFiles()
    {

        $this->configure('app');

    }


    /**
     * Get the base path for the application.
     *
     * @param string $path
     * @return string
     */
    public function basePath($path = '')
    {
        if (isset($this->basePath)) {
            return $this->basePath . ($path ? '/' . $path : $path);
        }

        if ($this->runningInConsole()) {
            $this->basePath = getcwd();
        } else {
            $this->basePath = realpath(getcwd() . '/../');
        }

        return $this->basePath($path);
    }


    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerConfigBindings()
    {
        $this->singleton('config', function () {
            return new Repository();
        });
    }

    protected function exceptionHandler($e)
    {


        $args = array(
            'response' => 500, // Specify the HTTP status code you want
            'back_link' => true, // Add a "back" link to return to the previous page
        );
        if (method_exists($e, "getStatusCode")) {
            $args["response"] = $e->getStatusCode();
        }

        wp_die($e->getMessage(), $e->getMessage(), $args);

    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {

        $abstract = $this->getAlias($abstract);

        if (!$this->bound($abstract) &&
            array_key_exists($abstract, $this->availableBindings) &&
            !array_key_exists($this->availableBindings[$abstract], $this->ranServiceBinders)) {
            $this->{$method = $this->availableBindings[$abstract]}();

            $this->ranServiceBinders[$method] = true;
        }

        return parent::make($abstract, $parameters);
    }


}
