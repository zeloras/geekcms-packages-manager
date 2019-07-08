<?php

namespace GeekCms\PackagesManager\Support\Components;

use BadMethodCallException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Validator;
use Nwidart\Modules\Module as MainModule;

/**
 * Class CoreComponent.
 *
 * @method Container getApp()
 * @method Container setApp(Container $app)
 * @method string    getNavname()
 * @method string    setNavname($name)
 * @method string    getModuleFacade()
 * @method string    setModuleFacade($name)
 * @method string    getName()
 * @method string    setName($name)
 * @method string    getPrefix()
 * @method string    setPrefix($prefix)
 * @method string    getAdminRoutePrefix()
 * @method string    setAdminRoutePrefix($prefix)
 * @method string    getDefer()
 * @method string    setDefer(bool $status)
 * @method string    getNamespaceName()
 * @method string    setNamespaceName($name)
 * @method string    getModulePath()
 * @method string    setModulePath($path)
 * @method string    getPath()
 * @method string    setPath($path)
 * @method string    getModuleConfig()
 * @method string    setModuleConfig(array $config)
 * @method string    getModuleLogs()
 * @method string    setModuleLogs(string $name)
 * @method string    getModuleStorageInstance()
 * @method string    setModuleStorageInstance(\Storage $name)
 * @method string    getResourcesStorageInstance()
 * @method string    setResourcesStorageInstance(\Storage $name)
 */
abstract class CoreComponent extends MainModule
{
    /**
     * This name using for get path to modules from config file.
     */
    const PATH_MODULES = 'modules';

    /**
     * This name using for get path to root/resources from config file.
     */
    const PATH_RESOURCES = 'resources';

    /**
     * This name using for set log channel.
     */
    const LOGS_CHANNEL = 'modules';

    /**
     * Path for load config.
     */
    const CONFIG_PATH = 'Config/config.php';

    /**
     * Base module name.
     *
     * @var null|string
     */
    protected $module_facade;

    /**
     * Main laravel $app.
     *
     * @var null|object
     */
    protected $app;

    /**
     * Menu name.
     *
     * @var string
     */
    protected $navname = '';

    /**
     * Module name.
     *
     * @var string
     */
    protected $name = 'module';

    /**
     * Prefix for configs, settings etc.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Prefix for admin routes.
     *
     * @var string
     */
    protected $admin_route_prefix = 'admin.';

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Namespace module name.
     *
     * @var string
     */
    protected $namespace_name = 'Module';

    /**
     * Module path from root.
     *
     * @var string
     */
    protected $module_path = '';

    /**
     * @internal
     */
    protected $path;

    /**
     * Will contain module settings.
     *
     * @var array
     */
    protected $module_config = [];

    /**
     * Config contain base paths for module components.
     *
     * @var array
     */
    public static $components_path = [
        'modules' => self::PATH_MODULES,
        'resources' => self::PATH_RESOURCES,
        'main_lang' => 'lang/modules/',
        'main_view' => 'views/modules/',
        'module_routes' => 'Http/routes.php',
        'module_lang' => 'Resources/lang',
        'module_view' => 'Resources/views',
        'module_factories' => 'Database/factories',
        'module_migrations' => 'Database/Migrations',
        'rules_map' => 'Models\\Validators\\Rules',
    ];

    /**
     * Logs instance.
     *
     * @var string
     */
    protected $module_logs;

    /**
     * Storage instances for work with filesystem in module dir.
     *
     * @var \Storage
     */
    protected $module_storage_instance;

    /**
     * Storage instances for work with filesystem in root/resources dir.
     *
     * @var \Storage
     */
    protected $resources_storage_instance;

    /**
     * CoreComponent constructor.
     *
     * CoreComponent constructor.
     * @param Container $app
     * @param null $name
     * @param null $path
     * @throws \Exception
     */
    public function __construct(Container $app, $name = null, $path = null)
    {
        if (empty($name) && empty($path)) {
            return false;
        }

        $this->setApp($app);
        $this->setPrefix(\Gcms::MODULES_PREFIX);
        $this->loadCoreComponents($name);
        $this->initVariables($this->getName(), $path);

        parent::__construct($app, $this->getName(), $this->getModulePath());
        parent::fireEvent('constructor');

        return true;
    }

    /**
     * Getter/setter for variables class.
     *
     * @param null  $variable
     * @param array $params
     *
     * @return mixed
     */
    public function __call($variable = null, $params = [])
    {
        $filter = preg_replace('/^get|^set/', '', $variable);
        $filter_under = preg_replace_callback('/_([^_]+)/imus', function ($m) {
            return ucfirst($m[1]);
        }, $filter);

        $filter_upper = preg_replace_callback('/([A-Z]{1})/mus', function ($m) {
            return '_'.lcfirst($m[1]);
        }, $filter);

        $filter_upper = preg_replace('/^_/', '', $filter_upper);

        if (!empty($filter_under) && property_exists(self::class, $filter_under) || !empty($filter_upper) && property_exists(self::class, $filter_upper)) {
            $filter = (property_exists(self::class, $filter_under)) ? $filter_under : $filter_upper;

            if (\count($params) && preg_match('/^set/', $variable)) {
                $this->{$filter} = $params[array_keys($params)[0]];
            }

            return $this->{$filter};
        }

        if (!method_exists(self::class, $variable)) {
            throw new BadMethodCallException("Method {$variable} does not exist.");
        }

        return \call_user_func_array($variable, $params);
    }

    /**
     * Main boot init.
     */
    public function boot()
    {
        $this->loadCoreComponents();
        $this->initVariables($this->getName(), $this->getPath());

        $this->registerConfig();
        $this->registerFiles();
        $this->registerAliases();
        $this->registerProviders();
        $this->registerFacades();

        $this->registerTranslations();
        $this->registerRoutes();
        $this->registerFactories();
        $this->registerMigrations();
        $this->registerBladeDirective();
        $this->registerViews();
        $this->registerValidationRules();

        parent::fireEvent('boot');
    }

    /**
     * Method for register module.
     *
     * @param null $main_name
     * @throws \Exception
     */
    public function register($main_name = null)
    {
        $this->loadCoreComponents($main_name);
        $this->initVariables($this->getName(), $this->getPath());

        $this->registerConfig();
        $this->registerFiles();
        $this->registerAliases();
        $this->registerProviders();
        $this->registerFacades();

        $this->registerTranslations();
        $this->registerRoutes();
        $this->registerFactories();
        $this->registerMigrations();
        $this->registerBladeDirective();
        $this->registerViews();
        $this->registerNavigation();
        $this->registerValidationRules();
        parent::fireEvent('register');
    }

    /**
     * Get menu data.
     *
     * @return string
     */
    public function getMenu()
    {
        return $this->get('menu_sidebar', null);
    }

    /**
     * Registration module config.
     *
     * @throws \Exception
     */
    public function registerConfig()
    {
        $config_path = $this->getModulePath().$this::CONFIG_PATH;
        if ($this->is_exists($this::CONFIG_PATH, ['is_file' => true])) {
            $this->publishes([
                $config_path => config_path($this->getPrefix().$this->getName().'.php'),
            ], 'config');

            $this->mergeConfigFrom(
                $config_path,
                $this->getPrefix().$this->getName()
            );

            $module_config = \Config::get($this->getPrefix().$this->getName(), []);

            if (!empty($module_config)) {
                $this->setModuleConfig($module_config);
                $module_config = null;
            }
        }
    }

    /**
     * For include helpers or something else.
     *
     * @throws \Exception
     */
    public function registerFiles()
    {
        try {
            $files = $this->get('files', []);
        } catch (\Exception $e) {
            $files = [];
        }

        foreach ($files as $file) {
            $path = base_path($this->getPath().\DIRECTORY_SEPARATOR.$file);

            if ($this->is_exists($path, ['is_file' => true])) {
                require $path;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerProviders()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function registerAliases()
    {
        try {
            $aliases = $this->get('aliases', []);
        } catch (\Exception $e) {
            $aliases = [];
        }

        $loader = AliasLoader::getInstance();
        foreach ($aliases as $aliasName => $aliasClass) {
            if (!class_exists($aliasName)) {
                $loader->alias($aliasName, $aliasClass);
            }
        }
    }

    /**
     * For register module facades.
     */
    public function registerFacades()
    {
    }

    /**
     * Register translations.
     */
    public function registerTranslations()
    {
        $langModulePath = $this->getModulePath().self::$components_path['module_lang'];

        if ($this->is_exists(self::$components_path['module_lang'])) {
            $this->loadTranslationsFrom($langModulePath, $this->getPrefix().$this->getName());
        }
    }

    /**
     * Register routes.
     *
     * @throws \Exception
     */
    public function registerRoutes()
    {
        $path_routes = $this->getModulePath().self::$components_path['module_routes'];
        if (!app()->routesAreCached()) {
            if ($this->is_exists(self::$components_path['module_routes'], ['is_file' => true])) {
                require_once $path_routes;
            }
        }
    }

    /**
     * Registration module factories.
     *
     * @throws \Exception
     */
    public function registerFactories()
    {
        $factory_path = $this->getModulePath().self::$components_path['module_factories'];

        if ($this->is_exists(self::$components_path['module_factories'])) {
            if (!app()->environment('production')) {
                app(Factory::class)->load($factory_path);
            }
        }
    }

    /**
     * Load module migrations.
     *
     * @throws \Exception
     */
    public function registerMigrations()
    {
        $migration_path = $this->getModulePath().self::$components_path['module_migrations'];
        if ($this->is_exists(self::$components_path['module_migrations'])) {
            $this->loadMigrationsFrom($migration_path);
        }
    }

    /**
     * Registration blade directive.
     */
    public function registerBladeDirective()
    {
    }

    /**
     * Register views.
     *
     * @throws \Exception
     */
    public function registerViews()
    {
        $view_path_main = resource_path(self::$components_path['main_view'].$this->getName());
        $view_path_module = $this->getModulePath().self::$components_path['module_view'];

        if ($this->is_exists(self::$components_path['module_view'])) {
            $this->publishes([
                $view_path_module => $view_path_main,
            ], 'views');

            $this->loadViewsFrom(array_merge(array_map(function ($path) {
                return $path.self::$components_path['modules'].\DIRECTORY_SEPARATOR.$this->getName();
            }, \Config::get('view.paths')), [$view_path_module]), $this->getName());
        }
    }

    /**
     * Register menu item in admin sidebar.
     */
    public function registerNavigation()
    {
    }

    /**
     * Get all unresolved requirements which don't initialized.
     *
     * @return array
     */
    public function getUnresolvedRequirements()
    {
        $requirements = [];
        $aliases = $this->getRequires();
        if ($aliases && \count($aliases)) {
            foreach ($aliases as $requirementName) {
                $requirements[$requirementName] = $this->getApp()->isAlias($requirementName);
            }
        }

        return $requirements;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getCachedServicesPath()
    {
        return $this->getApp()->getCachedServicesPath();
    }

    /**
     * Load bases components for work with module.
     *
     * @param null $main_name
     */
    protected function loadCoreComponents($main_name = null)
    {
        $preg_fnc = function ($value) {
            return preg_replace('/' . preg_quote(base_path(),DIRECTORY_SEPARATOR) . '\\/|\\/\*$/uims', '', $value);
        };
        $this->setName((empty($main_name)) ? $this->getName() : strtolower($main_name));
        $disk_name = $this::PATH_MODULES;

        if (class_exists('Module')) {
            $module_path = \Module::getModulePath($this->getName());
            $scaned_paths = array_map(function ($val) use ($module_path, $preg_fnc) {
                $preg_path = $preg_fnc($val);
                $preg_module = $preg_fnc(dirname($module_path, 1));
                return ($preg_path === $preg_module) ? strtolower($preg_module) : null;
            }, \Module::getScanPaths());

            $real_path = array_filter($scaned_paths, function ($value) {
                return !empty($value);
            });

            if (count($real_path)) {
                $disk_name_first = array_first($real_path);
                $this::$components_path['modules'] = $disk_name_first;
                $disk_name = isset($this->app['config']["filesystems.disks.{$disk_name_first}"]) ? $disk_name_first : $disk_name;
            }
        }

        if (!$this->getModuleLogs() instanceof \Log) {
            $this->setModuleLogs(\Log::channel($this::LOGS_CHANNEL));
        }

        if (class_exists('Storage')) {
            if (!$this->getModuleStorageInstance() instanceof \Storage) {
                $this->setModuleStorageInstance(\Storage::disk($disk_name));
            }

            if (!$this->getResourcesStorageInstance() instanceof \Storage) {
                $this->setResourcesStorageInstance(\Storage::disk($disk_name));
            }
        }
    }

    /**
     * Init main module data, like a name or root path.
     *
     * @param string $name
     * @param string $path
     *
     * @throws \Exception
     */
    protected function initVariables($name = null, $path = null)
    {
        try {
            if (empty($name) && empty($path)) {
                preg_match_all('/([^\\\]+\\\){1}(?<module>.*?)\\\/ims', static::class, $module_names);
            } else {
                preg_match_all('/(?<module>'.$name.')$/ims', $path, $module_names);
            }

            $this->setNamespaceName((isset($module_names['module'][0])) ? $module_names['module'][0] : $this->getNamespaceName());
            $this->setPath($this->getModuleStorageInstance()->path($this->getNamespaceName()).\DIRECTORY_SEPARATOR);
            $this->setName(strtolower($this->getNamespaceName()));
            $this->setModulePath($this->getPath());
            $this->setNavname($this->getPrefix().$this->getName().'::');
        } catch (\Exception $e) {
            $this->getModuleLogs()->error($e);

            throw new \Exception($e);
        }
    }

    /**
     * Register validation rules.
     */
    protected function registerValidationRules()
    {
        $basenamespace = ucfirst(self::PATH_MODULES).'\\'.$this->getNamespaceName().'\\'.self::$components_path['rules_map'];

        if (class_exists($basenamespace)) {
            Validator::resolver(function ($translator, $data, $rules, $messages) use ($basenamespace) {
                return new $basenamespace($translator, $data, $rules, $messages);
            });
        }
    }

    /**
     * Function for check file.
     *
     * @param string $path
     * @param array  ...$args Can contain array
     *                        with key-value:
     *                        is_file bool false - Check $path is file
     *                        instance string 'module' - Available check disks: self::PATH_RESOURCES, self::PATH_MODULES
     *                        create_dir bool true - If we check only directory, we can try to create folder in process
     *                        exception bool false - If file/dir not exists, show exception
     *                        exception_message string - Custom message for exception
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function is_exists(string $path = '', array ...$args)
    {
        $is_file = false;
        $create_dir = false;
        $exception = false;
        $exception_message = 'File or directory not exists:'.$path;
        $that_file = $that_dir = $status = $instance_init = false;
        $instance = $this->getModuleStorageInstance();

        if (\count($args)) {
            foreach ($args[0] as $key => $value) {
                if ('is_file' === $key) {
                    $is_file = (bool) $value;
                } elseif ('exception' === $key) {
                    $exception = (bool) $value;
                } elseif ('create_dir' === $key) {
                    $create_dir = (bool) $value;
                } elseif ('exception_message' === $key) {
                    $exception_message = (string) $value;
                } elseif ('instance' === $key) {
                    $instance = (self::PATH_RESOURCES === $value) ? $this->getResourcesStorageInstance() : $instance;
                    $instance_init = (self::PATH_RESOURCES === $value);
                }
            }
        }

        $path = (!$instance_init) ? $this->getNamespaceName().\DIRECTORY_SEPARATOR.$path : $path;

        $exists = $instance->exists($path);

        if ($exists) {
            $mime = $instance->getMimetype($path);

            if ('directory' === $mime) {
                $that_dir = true;
            } else {
                $that_file = true;
            }
        } else {
            if ($create_dir && !$that_dir && !$that_file) {
                $that_dir = $instance->makeDirectory($path);
            }

            if (!$exception && !$that_dir) {
                return $status;
            }
        }

        $status = ($is_file && $that_file || !$is_file && $that_dir);

        if ($exception && (!$exists || !$status)) {
            $this->getModuleLogs()->error($exception_message);

            throw new \Exception($exception_message);
        }

        return $status;
    }
}
