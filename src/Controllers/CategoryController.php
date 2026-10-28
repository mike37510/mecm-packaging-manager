<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\SoftwareRepository;
use App\Security\Role;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Software category administration (admin only) — forms live under Settings.
 */
final class CategoryController extends BaseController
{
    private const REDIRECT = '/settings#rubrique-softwares';

    public function store(
        Request $request,
        CategoryRepository $categories,
        AuditLogRepository $auditLogs,
    ): void {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', __('flash.category_name_required'));
            Response::redirect(self::REDIRECT);
        }
        if ($categories->exists($name)) {
            Session::flash('error', __('flash.category_exists'));
            Response::redirect(self::REDIRECT);
        }

        $id = $categories->create($name);
        $auditLogs->log($this->auth->id(), 'category.created', 'category', $id, ['name' => $name], $request->ip());
        Session::flash('success', __('flash.category_created'));
        Response::redirect(self::REDIRECT);
    }

    public function update(
        Request $request,
        CategoryRepository $categories,
        SoftwareRepository $softwares,
        AuditLogRepository $auditLogs,
        string $id,
    ): void {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $categoryId = (int) $id;
        $category = $categories->findById($categoryId);
        if ($category === null) {
            Session::flash('error', __('flash.category_not_found'));
            Response::redirect(self::REDIRECT);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', __('flash.category_name_required'));
            Response::redirect(self::REDIRECT);
        }

        $other = $categories->findByName($name);
        if ($other !== null && $other->id !== $categoryId) {
            Session::flash('error', __('flash.category_exists'));
            Response::redirect(self::REDIRECT);
        }

        $oldName = $category->name;
        $categories->update($categoryId, $name);
        if ($oldName !== $name) {
            $softwares->renameCategory($oldName, $name);
        }

        $auditLogs->log(
            $this->auth->id(),
            'category.updated',
            'category',
            $categoryId,
            ['old' => $oldName, 'name' => $name],
            $request->ip()
        );
        Session::flash('success', __('flash.category_updated'));
        Response::redirect(self::REDIRECT);
    }

    public function destroy(
        Request $request,
        CategoryRepository $categories,
        SoftwareRepository $softwares,
        AuditLogRepository $auditLogs,
        string $id,
    ): void {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $categoryId = (int) $id;
        $category = $categories->findById($categoryId);
        if ($category === null) {
            Session::flash('error', __('flash.category_not_found'));
            Response::redirect(self::REDIRECT);
        }

        $inUse = 0;
        foreach ($softwares->all() as $sw) {
            if ($sw->category === $category->name) {
                $inUse++;
            }
        }
        if ($inUse > 0) {
            Session::flash('error', __('flash.category_in_use', ['count' => (string) $inUse]));
            Response::redirect(self::REDIRECT);
        }

        $categories->delete($categoryId);
        $auditLogs->log(
            $this->auth->id(),
            'category.deleted',
            'category',
            $categoryId,
            ['name' => $category->name],
            $request->ip()
        );
        Session::flash('success', __('flash.category_deleted'));
        Response::redirect(self::REDIRECT);
    }
}
