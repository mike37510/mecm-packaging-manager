<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Software;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Repositories\PackageExportRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\UserRepository;

/**
 * Sends renewal reminder emails to the user who last packaged each overdue software.
 */
final class RenewalReminderService
{
    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly UserRepository $users,
        private readonly PackageExportRepository $exports,
        private readonly MailConfigService $mailConfig,
        private readonly MailService $mail,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   checked: int,
     *   due: int,
     *   sent: int,
     *   skipped: int,
     *   failures: list<string>
     * }
     */
    public function run(?int $actorId = null, ?string $ip = null): array
    {
        if (!$this->mailConfig->isEnabled()) {
            return [
                'ok' => false,
                'error' => __('flash.mail_disabled'),
                'checked' => 0,
                'due' => 0,
                'sent' => 0,
                'skipped' => 0,
                'failures' => [],
            ];
        }

        $checked = 0;
        $due = 0;
        $sent = 0;
        $skipped = 0;
        $failures = [];
        $cfg = $this->mailConfig->get();
        $baseUrl = rtrim((string) ($cfg['app_url'] ?? ''), '/');

        foreach ($this->softwares->all() as $software) {
            $checked++;
            if (!$software->needsRenewalReminder()) {
                continue;
            }
            $due++;

            $recipient = $this->resolveRecipient($software);
            if ($recipient === null) {
                $skipped++;
                $failures[] = __('mail.renewal_skip_no_user', ['name' => $software->name]);
                continue;
            }

            $days = $software->daysUntilDue();
            $daysLate = $days === null ? null : max(0, -$days);
            $link = $baseUrl !== '' ? $baseUrl . '/softwares/' . $software->id : '/softwares/' . $software->id;

            $subject = __('mail.renewal_subject', [
                'name' => $software->name,
                'version' => $software->currentVersion,
            ]);
            $text = __('mail.renewal_body', [
                'user' => $recipient->displayName,
                'name' => $software->name,
                'version' => $software->currentVersion,
                'frequency' => (string) $software->updateFrequencyDays,
                'last_review' => $software->lastPackagedAt ?? $software->lastSyncAt ?? __('common.never'),
                'days_late' => $daysLate !== null ? (string) $daysLate : '?',
                'url' => $link,
            ]);
            $html = nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);

            $result = $this->mail->send($recipient->email, $subject, $text, $html);
            if (empty($result['ok'])) {
                $skipped++;
                $failures[] = __('mail.renewal_send_failed', [
                    'name' => $software->name,
                    'error' => (string) ($result['error'] ?? 'error'),
                ]);
                continue;
            }

            $this->softwares->patch($software->id, [
                'renewal_notified_at' => date('Y-m-d H:i:s'),
            ]);
            $this->auditLogs->log($actorId, 'mail.renewal_sent', 'software', $software->id, [
                'to' => $recipient->email,
                'user_id' => $recipient->id,
                'version' => $software->currentVersion,
            ], $ip);
            $sent++;
        }

        return [
            'ok' => true,
            'checked' => $checked,
            'due' => $due,
            'sent' => $sent,
            'skipped' => $skipped,
            'failures' => $failures,
        ];
    }

    private function resolveRecipient(Software $software): ?User
    {
        $userId = $software->lastPackagedBy;
        if ($userId === null || $userId < 1) {
            $latest = $this->exports->latestForSoftware($software->id);
            $userId = $latest?->createdBy;
        }
        if ($userId === null || $userId < 1) {
            return null;
        }

        $user = $this->users->findById($userId);
        if ($user === null || !$user->isActive()) {
            return null;
        }
        if ($user->email === '' || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $user;
    }
}
