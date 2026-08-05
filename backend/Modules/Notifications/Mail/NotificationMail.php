<?php

namespace Modules\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Models\Notification;

/**
 * بريد إشعار (Notifications / B2 · PR-3) — نسخة بريدية من إشعارٍ داخلي مُنشأ سلفاً.
 *
 * يُرسَل فقط لأنواع مُدرَجة في القائمة البيضاء (config('notifications.email_types'))، وهذا
 * القرار يُتَّخذ في NotificationService لا هنا. عنوان المُرسِل عالمي من config/mail.php
 * (MAIL_FROM_*)، فلا نضبطه هنا. القالب RTL بسيط يعكس نفس العنوان/النص الظاهر بالتطبيق.
 */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'title' => $this->notification->title,
                'body' => $this->notification->body,
            ],
        );
    }
}
