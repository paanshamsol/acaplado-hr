<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->string('action')->toString();
        $action = in_array($action, ['created', 'updated', 'deleted', ''], true) ? $action : '';

        $model = $request->string('model')->toString();

        $logs = AuditLog::query()
            ->with('user')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($model !== '', fn ($q) => $q->where('auditable_type', $model))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $modelOptions = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->whereNotNull('auditable_type')
            ->orderBy('auditable_type')
            ->pluck('auditable_type');

        return view('admin.audit-logs.index', compact('logs', 'action', 'model', 'modelOptions'));
    }
}
