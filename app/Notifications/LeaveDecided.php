<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveDecided extends Notification
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
        $status = $this->leaveRequest->status === 'approved' ? __('approved') : __('rejected');

        return [
            'message' => __('Your :type leave for :dates has been :status.', [
                'type' => $this->leaveRequest->leaveType?->name ?? __('Leave'),
                'dates' => $this->leaveRequest->start_date?->format('d/m/Y').' - '.$this->leaveRequest->end_date?->format('d/m/Y'),
                'status' => $status,
            ]),
            'link' => route('staff.leaves.index'),
        ];
    }
}
