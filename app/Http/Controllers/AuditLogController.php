<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'event' => ['nullable', Rule::in(array_keys(AuditLog::events()))],
            'type' => ['nullable', Rule::in(array_keys(AuditLog::types()))],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::with('user')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->event))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->type))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%' . $request->q . '%'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
