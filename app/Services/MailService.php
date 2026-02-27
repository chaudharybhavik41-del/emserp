<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\MailProfile;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Resolve the best mail profile.
     *
     * Priority:
     * 1) Explicit profile code via $usage (e.g. "purchaseRfq")
     * 2) Department default profile (department_id + is_default)
     * 3) Company default profile (company_id + is_default)
     * 4) Global default profile (is_default)
     */
    public function getProfile(Company|int|null $company = null, Department|int|null $department = null, ?string $usage = null): ?MailProfile
    {
        $companyModel = $this->normalizeCompany($company);
        $deptModel    = $this->normalizeDepartment($department);

        // 1) Explicit usage/profile code (code is unique)
        if (!empty($usage)) {
            $byCode = MailProfile::query()
                ->where('code', $usage)
                ->where('is_active', true)
                ->first();

            if ($byCode) {
                return $byCode;
            }
        }

        // 2) Department default profile
        if ($deptModel) {
            $deptProfile = MailProfile::query()
                ->where('department_id', $deptModel->id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($deptProfile) {
                return $deptProfile;
            }
        }

        // 3) Company default profile
        if ($companyModel) {
            $companyProfile = MailProfile::query()
                ->where('company_id', $companyModel->id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($companyProfile) {
                return $companyProfile;
            }
        }

        // 4) Global default profile
        return MailProfile::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    protected function applyProfile(MailProfile $profile): void
    {
        // Laravel mailer config keys
        Config::set('mail.mailers.smtp.host', $profile->smtp_host);
        Config::set('mail.mailers.smtp.port', $profile->smtp_port);
        Config::set('mail.mailers.smtp.encryption', $profile->smtp_encryption);
        Config::set('mail.mailers.smtp.username', $profile->smtp_username);
        Config::set('mail.mailers.smtp.password', $profile->smtp_password);

        if (!empty($profile->from_email)) {
            Config::set('mail.from.address', $profile->from_email);
        }
        if (!empty($profile->from_name)) {
            Config::set('mail.from.name', $profile->from_name);
        }

        if (!empty($profile->reply_to)) {
            Config::set('mail.reply_to.address', $profile->reply_to);
            Config::set('mail.reply_to.name', $profile->from_name ?: (string) config('mail.from.name'));
        }
    }

    /**
     * Send an email using a Mail Template (DB) + resolved Mail Profile.
     */
    public function sendTemplate(
        string $templateCode,
        User|string $to,
        array $data = [],
        ?Company $company = null,
        ?Department $department = null,
        ?string $usage = null
    ): void {
        $template = MailTemplate::query()
            ->where('code', $templateCode)
            ->where('is_active', true)
            ->firstOrFail();

        // Prefer template's own profile (if set)
        $profile = null;
        if (!empty($template->mail_profile_id)) {
            $profile = MailProfile::query()
                ->where('id', $template->mail_profile_id)
                ->where('is_active', true)
                ->first();
        }

        // Fallback to resolver
        if (!$profile) {
            $profile = $this->getProfile($company, $department, $usage);
        }

        if ($profile) {
            $this->applyProfile($profile);
        }

        $toEmail = $to instanceof User ? $to->email : $to;

        $mailable = new class($template, $data) extends Mailable {
            public function __construct(
                protected MailTemplate $template,
                protected array $data
            ) {}

            public function build()
            {
                $subject = $this->replaceVars((string) ($this->template->subject ?? ''), $this->data);
                $body    = $this->replaceVars((string) ($this->template->body ?? ''), $this->data);

                $plain = trim(strip_tags($body));

                $mail = $this->subject($subject)
                    ->html($body);

                // Plain fallback
                if ($plain !== '') {
                    $mail->text('mail.raw_text', ['content' => $plain]);
                }

                return $mail;
            }

            protected function replaceVars(string $content, array $vars): string
            {
                foreach ($vars as $key => $value) {
                    $pattern = '/{{\s*' . preg_quote((string) $key, '/') . '\s*}}/';
                    $content = preg_replace($pattern, (string) $value, $content);
                }
                return $content;
            }
        };

        // Use smtp mailer explicitly (profile fields were applied to smtp mailer)
        Mail::mailer('smtp')->to($toEmail)->send($mailable);
    }

    /**
     * Send a mail template with attachments.
     *
     * IMPORTANT: This method is required by PurchaseOrderController::sendEmail().
     */
    public function sendTemplateWithAttachments(
        string $templateCode,
        string $toEmail,
        ?string $toName = null,
        array $data = [],
        ?string $usage = null,
        Company|int|null $companyId = null,
        Department|int|null $departmentId = null,
        array $attachments = []
    ): void {
        // Defensive: avoid passing a name/garbage instead of an email
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid recipient email: ' . $toEmail);
        }

        $template = MailTemplate::query()
            ->where('code', $templateCode)
            ->where('is_active', true)
            ->firstOrFail();

        // Prefer template profile (selected in template module UI)
        $profile = null;
        if (!empty($template->mail_profile_id)) {
            $profile = MailProfile::query()
                ->where('id', $template->mail_profile_id)
                ->where('is_active', true)
                ->first();
        }

        // Fallback to resolver
        if (!$profile) {
            $company    = $this->normalizeCompany($companyId);
            $department = $this->normalizeDepartment($departmentId);
            $profile    = $this->getProfile($company, $department, $usage);
        }

        if (!$profile) {
            throw new \RuntimeException('No active mail profile found for sending email.');
        }

        $this->applyProfile($profile);

        // NOTE: Laravel's Illuminate\Mail\Mailable already has a public `$attachments` property.
        // Declaring our own `$attachments` property with a different visibility causes a fatal error.
        // So we store incoming attachments in a different property.
        $mailable = new class($template, $data, $attachments) extends Mailable {
            public function __construct(
                protected MailTemplate $template,
                protected array $data,
                protected array $customAttachments
            ) {}

            public function build()
            {
                $subject = $this->replaceVars((string) ($this->template->subject ?? ''), $this->data);
                $body    = $this->replaceVars((string) ($this->template->body ?? ''), $this->data);

                $plain = trim(strip_tags($body));

                $mail = $this->subject($subject)
                    ->html($body);

                // Plain fallback
                if ($plain !== '') {
                    $mail->text('mail.raw_text', ['content' => $plain]);
                }

                foreach ($this->customAttachments as $attachment) {
                    // 1) String path
                    if (is_string($attachment)) {
                        $mail->attach($attachment);
                        continue;
                    }

                    // 2) Array path/options
                    if (is_array($attachment)) {
                        // attachData support
                        if (isset($attachment['data'])) {
                            $name = $attachment['name'] ?? 'attachment';
                            $opts = [];
                            if (!empty($attachment['mime'])) {
                                $opts['mime'] = $attachment['mime'];
                            }
                            $mail->attachData($attachment['data'], $name, $opts);
                            continue;
                        }

                        if (!empty($attachment['path'])) {
                            $options = [];
                            if (!empty($attachment['name'])) {
                                $options['as'] = $attachment['name'];
                            }
                            if (!empty($attachment['mime'])) {
                                $options['mime'] = $attachment['mime'];
                            }
                            $mail->attach($attachment['path'], $options);
                            continue;
                        }
                    }
                }

                // Reply-To (if profile configured)
                $replyTo = config('mail.reply_to.address');
                if (!empty($replyTo)) {
                    $mail->replyTo($replyTo, (string) (config('mail.reply_to.name') ?? ''));
                }

                return $mail;
            }

            protected function replaceVars(string $content, array $vars): string
            {
                foreach ($vars as $key => $value) {
                    $pattern = '/{{\s*' . preg_quote((string) $key, '/') . '\s*}}/';
                    $content = preg_replace($pattern, (string) $value, $content);
                }
                return $content;
            }
        };

        // Use smtp mailer explicitly (profile fields were applied to smtp mailer)
        // NOTE: We use the (email, name) signature instead of an associative array because
        // some environments interpret array values as the address (causing "Name" to be used as email).
        $name = $toName ? trim($toName) : null;

        if (!empty($name)) {
            Mail::mailer('smtp')->to($toEmail, $name)->send($mailable);
        } else {
            Mail::mailer('smtp')->to($toEmail)->send($mailable);
        }
    }

    protected function normalizeCompany(Company|int|null $company): ?Company
    {
        if ($company instanceof Company) {
            return $company;
        }
        if (is_int($company)) {
            return Company::find($company);
        }
        return null;
    }

    protected function normalizeDepartment(Department|int|null $department): ?Department
    {
        if ($department instanceof Department) {
            return $department;
        }
        if (is_int($department)) {
            return Department::find($department);
        }
        return null;
    }
}
