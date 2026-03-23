<?php

namespace App\Http\Controllers;

use App\Models\ProjectInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptProjectInvitationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $invitation = ProjectInvitation::query()->where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            abort(410, __('This invitation has expired.'));
        }

        if (! $request->user()) {
            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }

        $user = $request->user();

        if (strcasecmp((string) $user->email, $invitation->email) !== 0) {
            abort(403, __('This invitation was sent to a different email address.'));
        }

        $project = $invitation->project;

        $project->members()->syncWithoutDetaching([
            $user->id => ['role' => $invitation->role],
        ]);

        $invitation->delete();

        return redirect()->route('projects.show', $project);
    }
}
