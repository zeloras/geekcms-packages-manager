<?php

namespace GeekCms\PackagesManager\Http\Controllers;

use PackageSystem;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use GeekCms\PackagesManager\Exceptions\ModuleNotFoundException;

/**
 * Class AdminController.
 */
class AdminController extends Controller
{
    /**
     * Main route with installed packages.
     *
     * @return Factory|View
     * @throws ModuleNotFoundException
     *
     */
    public function index()
    {
        $main = PackageSystem::getModulesOfficial();
        $list = $main->available();

        return view('packagesmanager::admin/index', [
            'list' => $list,
        ]);
    }

    /**
     * Route for show page with available modules list.
     *
     * @return Factory|View
     */
    public function list()
    {
        return view('packagesmanager::admin/list', [
            'list' => [],
        ]);
    }

    /**
     * Method for change module state
     *
     * @param null $module
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Throwable
     */
    public function changeActive($module = null)
    {
        $find_module = PackageSystem::has($module);
        if ($find_module) {
            $find_module = PackageSystem::find($module);
            if ($find_module->enabled()) {
                $find_module->disable();
            } else {
                $find_module->enable();
            }

            \Gcms::syncPermissionsList();
        }

        return redirect()->back();
    }

    /**
     * Method for change module state
     *
     * @param null $module
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Throwable
     */
    public function changeInstall($module = null)
    {
        $find_module = PackageSystem::has($module);
        if ($find_module) {
            $find_module = PackageSystem::find($module);
            $find_module->delete();
        } else {
            PackageSystem::findAndInstall($module);
            //install
        }

        \Gcms::syncPermissionsList();

        return redirect()->back();
    }
}
