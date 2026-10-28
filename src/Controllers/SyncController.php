<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Repositories\AuditLogRepository;
use App\Repositories\SyncRepository;
use App\Services\SyncService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Remote installer sync / update detection.
 */
final class SyncController extends BaseController
{
    public function check(Request $request, SyncService $sync, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $sync->check((int) $id, $this->auth->id(), $request->ip());
        $wantsJson = $this->wantsJson($request);

        if (!$result['ok']) {
            if ($wantsJson) {
                Response::json([
                    'ok' => false,
                    'error' => $result['error'] ?? __('flash.sync_failed'),
                    'software_id' => (int) $id,
                    'status' => 'error',
                    'status_label' => __('sync_status.error'),
                    'status_badge' => 'text-bg-danger',
                ], 422);
                return;
            }
            Session::flash('error', $result['error'] ?? __('flash.sync_failed'));
            Response::redirect($this->redirectTarget($request, (int) $id));
        }

        $status = (string) ($result['status'] ?? 'up_to_date');
        $label = match ($status) {
            'update_available' => __('sync_status.update_available'),
            'up_to_date' => __('sync_status.up_to_date'),
            'error' => __('sync_status.error'),
            default => __('sync_status.never'),
        };
        $badge = match ($status) {
            'update_available' => 'text-bg-warning text-dark',
            'up_to_date' => 'text-bg-success',
            'error' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };

        if ($wantsJson) {
            Response::json([
                'ok' => true,
                'software_id' => (int) $id,
                'status' => $status,
                'status_label' => $label,
                'status_badge' => $badge,
                'update_available' => !empty($result['update_available']),
                'message' => $result['message'] ?? '',
                'sha256' => $result['sha256'] ?? null,
                'file_name' => $result['file_name'] ?? null,
                'remote_version' => $result['remote_version'] ?? null,
                'current_version' => $result['remote_version'] ?? null,
                'last_sync_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $remoteVersion = !empty($result['remote_version']) ? (string) $result['remote_version'] : '';
        $message = trim((string) ($result['message'] ?? ''));

        if (!empty($result['update_available'])) {
            if ($remoteVersion !== '') {
                Session::flash('success', __('flash.sync_update_available', [
                    'message' => $message,
                    'version' => $remoteVersion,
                ]));
            } else {
                Session::flash(
                    'success',
                    $message !== ''
                        ? $message . ' ' . __('flash.sync_update_available_short')
                        : __('flash.sync_update_available_short')
                );
            }
        } elseif ($remoteVersion !== '') {
            Session::flash('success', __('flash.sync_up_to_date', [
                'message' => $message,
                'version' => $remoteVersion,
            ]));
        } else {
            Session::flash(
                'success',
                $message !== ''
                    ? $message . ' ' . __('flash.sync_up_to_date_short')
                    : __('flash.sync_up_to_date_short')
            );
        }

        Response::redirect($this->redirectTarget($request, (int) $id));
    }

    public function deleteHistory(
        Request $request,
        SyncRepository $syncs,
        AuditLogRepository $auditLogs,
        string $id,
        string $syncId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $softwareId = (int) $id;
        $row = $syncs->findById((int) $syncId);
        if ($row === null || $row->softwareId !== $softwareId) {
            Session::flash('error', __('flash.sync_not_found'));
            Response::redirect('/softwares/' . $softwareId . '?tab=sync');
        }

        $syncs->delete($row->id);
        $auditLogs->log(
            $this->auth->id(),
            'sync.history_deleted',
            'software',
            $softwareId,
            ['sync_id' => $row->id, 'status' => $row->status, 'file_name' => $row->fileName],
            $request->ip()
        );

        Session::flash('success', __('flash.sync_history_deleted'));
        Response::redirect('/softwares/' . $softwareId . '?tab=sync');
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->isAjax()) {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json');
    }

    private function redirectTarget(Request $request, int $id): string
    {
        $back = trim((string) $request->input('redirect', ''));
        if ($back === 'list' || $back === '/softwares') {
            return '/softwares';
        }

        return '/softwares/' . $id . '?tab=sync';
    }
}
