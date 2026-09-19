<?php

namespace App\Http\Controllers\V1;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return NotificationResource::collection(
            $request->user()->notifications()->latest('sent_at')->paginate(20)
        );
    }

    public function markAsRead(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);
        $notification->update(['status' => NotificationStatus::READ]);
        return response()->noContent();
    }
}
