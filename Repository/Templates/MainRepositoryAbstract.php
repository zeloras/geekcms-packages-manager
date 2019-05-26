<?php

namespace GeekCms\PackagesManager\Repository\Template;

use GeekCms\PackagesManager\Repository\MainRepository;
use Illuminate\Container\Container;
use Nwidart\Modules\FileRepository as ModuleRepository;
use GeekCms\PackagesManager\Repository\LocalRepository;
use GeekCms\PackagesManager\Repository\RemoteRepository;
use Nwidart\Modules\Laravel\Module;

abstract class MainRepositoryAbstract extends ModuleRepository
{
    /**
     * Constants for filter something or get lists with needed packages
     */
    const PACKAGE_OFFICIAL = 'official';
    const PACKAGE_UNOFFICIAL = 'unofficial';
    const PACKAGE_INSTALLED_ALL = 'all';
    const PACKAGE_INSTALLED_ACTIVE = 'installed-active';
    const PACKAGE_INSTALLED_DISABLED = 'installed-disabled';
    const PACKAGE_REMOTE_OFFICIAL = 'remote-official';
    const PACKAGE_REMOTE_UNOFFICIAL = 'remote-unofficial';

    const REPO_USER_LINK = 'https://api.github.com/users/*name*/repos';
    const REPO_GROUP_LINK = 'https://api.github.com/orgs/*name*/repos';

    /**
     * Load classes for work with remote packages or local(downloaded)
     *
     * @var string
     */
    protected $packages_local = LocalRepository::class;
    protected $packages_remote = RemoteRepository::class;

    /**
     * Repositories with official and unofficial modules
     *
     * @var array
     */
    protected $modules = [self::PACKAGE_OFFICIAL => [], self::PACKAGE_UNOFFICIAL => []];

    /**
     * For switch to back main class
     *
     * @var
     */
    protected $main_instance;

    /**
     * MainRepositoryAbstract constructor.
     * @param Container $app
     * @param null $path
     * @param MainRepository $instance
     */
    public function __construct(Container $app, $path = null, MainRepository $instance = null)
    {
        $this->main_instance = $instance;
        parent::__construct($app, $path);
    }

    /**
     * @inheritDoc
     */
    protected function createModule(...$args)
    {
        return new Module(...$args);
    }

    /**
     * Get official packages list
     *
     * @return mixed
     * @throws \Nwidart\Modules\Exceptions\ModuleNotFoundException
     */
    public function getOfficialPackages()
    {
        return [];
    }

    /**
     * Get unofficial packages list
     *
     * @return mixed
     * @throws \Nwidart\Modules\Exceptions\ModuleNotFoundException
     */
    public function getUnofficialPackages()
    {
        return [];
    }

    /**
     * Switch to main class
     *
     * @return MainRepository
     */
    public function setMain()
    {
        return $this->main_instance;
    }

}