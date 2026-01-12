<?php

namespace App\Notifications;

use App\Models\ComplianceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplianceAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected ComplianceAlert $alert;

    /**
     * Create a new notification instance.
     */
    public function __construct(ComplianceAlert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $record = $this->alert->complianceRecord;
        $vehicle = $this->alert->vehicle;
        $complianceType = $record->complianceType;

        $subject = $this->getSubject();
        $message = new MailMessage();

        $message->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getIntroLine());

        // Vehicle details
        $message->line("**Vehicle:** {$vehicle->registration_number} - {$vehicle->make} {$vehicle->model}")
            ->line("**Compliance Type:** {$complianceType->name}");

        // Expiry details
        if ($this->alert->alert_type === 'expired') {
            $daysOverdue = abs($this->alert->days_until_expiry);
            $message->line("**Status:** EXPIRED - {$daysOverdue} days overdue")
                ->line("**Expired On:** {$record->expiry_date->format('d/m/Y')}");
        } else {
            $message->line("**Expires In:** {$this->alert->days_until_expiry} days")
                ->line("**Expiry Date:** {$record->expiry_date->format('d/m/Y')}");
        }

        // Issue date if available
        if ($record->issue_date) {
            $message->line("**Issue Date:** {$record->issue_date->format('d/m/Y')}");
        }

        // Action required
        $message->line($this->getActionLine())
            ->action('View Compliance Details', url("/vehicles/{$vehicle->id}/compliance"))
            ->line('Thank you for keeping your fleet compliant!');

        // Set importance based on alert type
        if (in_array($this->alert->alert_type, ['expired', 'expiring_7'])) {
            $message->priority(1); // High priority
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $record = $this->alert->complianceRecord;
        $vehicle = $this->alert->vehicle;

        return [
            'alert_id' => $this->alert->id,
            'alert_type' => $this->alert->alert_type,
            'compliance_record_id' => $record->id,
            'vehicle_id' => $vehicle->id,
            'vehicle_registration' => $vehicle->registration_number,
            'compliance_type' => $record->complianceType->name,
            'days_until_expiry' => $this->alert->days_until_expiry,
            'expiry_date' => $record->expiry_date?->toDateString(),
            'message' => $this->getIntroLine(),
        ];
    }

    /**
     * Get email subject based on alert type
     */
    private function getSubject(): string
    {
        $vehicle = $this->alert->vehicle;
        $complianceType = $this->alert->complianceRecord->complianceType;

        return match ($this->alert->alert_type) {
            'expired' => "🚨 URGENT: Compliance Expired - {$vehicle->registration_number}",
            'expiring_7' => "⚠️ URGENT: Compliance Expiring in 7 Days - {$vehicle->registration_number}",
            'expiring_14' => "⚠️ Compliance Expiring in 14 Days - {$vehicle->registration_number}",
            'expiring_30' => "⏰ Compliance Expiring in 30 Days - {$vehicle->registration_number}",
            'escalation' => "🔴 ESCALATION: Compliance Issue - {$vehicle->registration_number}",
            default => "Compliance Alert - {$vehicle->registration_number}",
        };
    }

    /**
     * Get intro line based on alert type
     */
    private function getIntroLine(): string
    {
        $complianceType = $this->alert->complianceRecord->complianceType->name;

        return match ($this->alert->alert_type) {
            'expired' => "The {$complianceType} compliance for your vehicle has expired. Immediate action is required.",
            'expiring_7' => "The {$complianceType} compliance for your vehicle will expire in 7 days. Urgent action required.",
            'expiring_14' => "The {$complianceType} compliance for your vehicle will expire in 14 days. Please take action soon.",
            'expiring_30' => "The {$complianceType} compliance for your vehicle will expire in 30 days. Please plan for renewal.",
            'escalation' => "This is an escalation notice for an overdue {$complianceType} compliance issue.",
            default => "A compliance alert has been triggered for your vehicle.",
        };
    }

    /**
     * Get action line based on alert type
     */
    private function getActionLine(): string
    {
        return match ($this->alert->alert_type) {
            'expired' => "⚠️ **ACTION REQUIRED:** Please renew this compliance immediately to maintain vehicle operational status.",
            'expiring_7' => "⚠️ **URGENT:** Please schedule renewal within the next 7 days.",
            'expiring_14' => "Please schedule renewal within the next 14 days to avoid expiration.",
            'expiring_30' => "Please plan for renewal within the next 30 days.",
            'escalation' => "⚠️ **ESCALATION:** This issue requires immediate management attention.",
            default => "Please review and take appropriate action.",
        };
    }
}