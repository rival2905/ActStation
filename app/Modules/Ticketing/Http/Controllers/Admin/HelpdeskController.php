<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ticketing\Http\Requests\Admin\AssignHelpdeskRequest;
use App\Modules\Ticketing\Http\Requests\Admin\ReplyHelpdeskRequest;
use App\Modules\Ticketing\Http\Requests\Admin\UpdateHelpdeskStatusRequest;
use App\Modules\Ticketing\Models\HelpdeskTicket;
use App\Modules\Ticketing\Services\HelpdeskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpdeskController extends Controller
{
    public function index(Request $request): View
    {
        $query = HelpdeskTicket::query()->with(['user', 'assignedTo']);
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $helpdeskTickets = $query->latest()->paginate(15)->withQueryString();
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        return view('admin.helpdesk.index', compact('helpdeskTickets', 'statuses', 'priorities'));
    }

    public function show(HelpdeskTicket $helpdeskTicket): View
    {
        $helpdeskTicket->load(['user', 'assignedTo', 'replies.user']);

        $staffUsers = User::query()
            ->role(['admin', 'staff', 'superadmin'])
            ->get(['id', 'name', 'email']);

        return view('admin.helpdesk.show', compact('helpdeskTicket', 'staffUsers'));
    }

    public function assign(AssignHelpdeskRequest $request, HelpdeskTicket $helpdeskTicket, HelpdeskService $service): RedirectResponse
    {
        $assignedTo = $request->filled('assigned_to') ? (int) $request->input('assigned_to') : null;
        $service->assign($helpdeskTicket, $assignedTo);

        return back()->with('success', 'Tiket helpdesk berhasil ditugaskan.');
    }

    public function reply(ReplyHelpdeskRequest $request, HelpdeskTicket $helpdeskTicket, HelpdeskService $service): RedirectResponse
    {
        $service->reply($helpdeskTicket, (int) auth()->id(), $request->input('body'));

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function updateStatus(UpdateHelpdeskStatusRequest $request, HelpdeskTicket $helpdeskTicket, HelpdeskService $service): RedirectResponse
    {
        $service->updateStatus($helpdeskTicket, $request->input('status'));

        return back()->with('success', 'Status tiket helpdesk berhasil diperbarui.');
    }
}
