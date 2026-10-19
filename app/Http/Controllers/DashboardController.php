<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * Redirect the authenticated user to the dashboard for their role.
     */
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'hr'])) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('staff.dashboard');
    }
}
