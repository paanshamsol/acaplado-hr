<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequested extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('New :type leave request from :name (:days day(s)).', [
                'type' => $this->leaveRequest->leaveType?->name ?? __('Leave'),
                'name' => $this->leaveRequest->user?->name ?? __('Former Staff'),
                'days' => (float) $this->leaveRequest->days,
            ]),
            'link' => route('admin.leave-requests.index'),
        ];
    }
}
