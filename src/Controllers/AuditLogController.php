<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\UserRepository;
use App\Security\Role;
use App\Support\Request;

/**
 * Global activity / audit logs viewer.
 */
final class AuditLogController extends BaseController
{
    public function index(
        Request $request,
        AuditLogRepository $auditLogs,
        UserRepository $users,
        SoftwareRepository $softwares,
    ): void {
        $this->auth->requireRole(Role::Admin);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'user_id' => trim((string) $request->query('user_id', '')),
            'action_group' => trim((string) $request->query('action_group', '')),
            'software_id' => trim((string) $request->query('software_id', '')),
        ];

        $criteria = array_filter([
            'q' => $filters['q'],
            'user_id' => $filters['user_id'] !== '' ? (int) $filters['user_id'] : null,
            'action_group' => $filters['action_group'] !== '' ? $filters['action_group'] : null,
            'software_id' => $filters['software_id'] !== '' ? (int) $filters['software_id'] : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $rows = $auditLogs->search($criteria, 300);
        $softwareNames = [];
        foreach ($softwares->all() as $sw) {
            $softwareNames[$sw->id] = $sw->name;
        }

        foreach ($rows as &$row) {
            $row['action_label'] = self::actionLabel((string) ($row['action'] ?? ''));
            $entityType = (string) ($row['entity_type'] ?? '');
            $entityId = (int) ($row['entity_id'] ?? 0);
            $row['entity_label'] = null;
            $row['software_url'] = null;
            if ($entityType === 'software' && $entityId > 0) {
                $name = $softwareNames[$entityId] ?? ('#' . $entityId);
                $row['entity_label'] = $name;
                $row['software_url'] = '/softwares/' . $entityId;
            } elseif ($entityType === 'user' && $entityId > 0) {
                $row['entity_label'] = (string) ($row['display_name'] ?? ('user #' . $entityId));
            }
            $payload = $row['payload'] ?? null;
            if (is_string($payload) && $payload !== '') {
                $decoded = json_decode($payload, true);
                $row['payload_data'] = is_array($decoded) ? $decoded : null;
            } elseif (is_array($payload)) {
                $row['payload_data'] = $payload;
            } else {
                $row['payload_data'] = null;
            }
        }
        unset($row);

        $this->render('logs/index', [
            'logs' => $rows,
            'filters' => $filters,
            'users' => $users->all(),
            'softwares' => $softwares->all(),
            'actionGroups' => self::actionGroups(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function actionGroups(): array
    {
        return [
            'auth' => 'Authentification',
            'software' => 'Logiciels',
            'sync' => 'Synchro',
            'package' => 'ZIP / exports',
            'vm' => 'Tests VM',
            'procedure' => 'Procédures',
            'user' => 'Utilisateurs',
            'settings' => 'Configuration',
        ];
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'auth.login' => 'Connexion',
            'auth.logout' => 'Déconnexion',
            'auth.login_failed' => 'Échec de connexion',
            'software.created' => 'Logiciel créé',
            'software.updated' => 'Logiciel modifié',
            'software.deleted' => 'Logiciel supprimé',
            'software.icon_updated' => 'Icône mise à jour',
            'software.sync_up_to_date' => 'Synchro : à jour',
            'software.sync_update_available' => 'Synchro : mise à jour dispo',
            'software.export_zip' => 'ZIP généré',
            'software.export_deleted' => 'ZIP historique supprimé',
            'package.zip_exported' => 'ZIP généré',
            'package.zip_deleted' => 'ZIP supprimé',
            'package.export_created' => 'ZIP généré',
            'package.export_deleted' => 'ZIP supprimé',
            'vm.validation.passed' => 'Test VM réussi',
            'vm.validation.failed' => 'Test VM échoué',
            'procedure.updated' => 'Procédure mise à jour',
            'procedure.step_added' => 'Étape ajoutée',
            'procedure.step_updated' => 'Étape modifiée',
            'procedure.step_deleted' => 'Étape supprimée',
            'procedure.reordered' => 'Étapes réordonnées',
            'user.created' => 'Utilisateur créé',
            'user.updated' => 'Utilisateur modifié',
            'user.deleted' => 'Utilisateur supprimé',
            'settings.vm_updated' => 'Config VM / SSH mise à jour',
            'account.vm_updated' => 'VM de test personnelle mise à jour',
            'account.vm_migrated_from_global' => 'VM globale migrée vers le compte',
            'settings.mecm_updated' => 'Config MECM mise à jour',
            'driver.created' => 'Driver créé',
            'driver.updated' => 'Driver modifié',
            'driver.deleted' => 'Driver supprimé',
            'driver.sync_up_to_date' => 'Driver synchro : à jour',
            'driver.sync_update_available' => 'Driver synchro : hash différent',
            'driver.sync_error' => 'Driver synchro : erreur',
            'driver.zip_exported' => 'ZIP driver généré',
            default => $action,
        };
    }
}
