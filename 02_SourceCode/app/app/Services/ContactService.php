<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Support\BusinessLog;

/** Lưu tin nhắn liên hệ (trạng thái new). Bẫy honeypot được xử lý ở controller (giả thành công, không lưu). */
class ContactService
{
    public function store(array $data, ?string $ip): ContactMessage
    {
        $message = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'new',
            'ip' => $ip,
        ]);
        BusinessLog::info('contact.received', ['message_id' => $message->id]);

        return $message;
    }
}
