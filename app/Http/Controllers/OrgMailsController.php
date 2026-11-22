<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The Google mailboxes of the organization, taken from the accounts in this app.
 * A click opens Google's own account chooser for that address. This page never sees a password
 * and never signs in as someone else.
 */
class OrgMailsController extends Controller
{
    private const DOMAIN = '5core.com';

    public function index()
    {
        return view('org-mails.index', ['domain' => self::DOMAIN]);
    }

    public function data(Request $request)
    {
        $columns = ['id', 'name', 'email', 'is_active'];
        foreach (['role', 'designation', 'google_id', 'deactivated_at'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        $rows = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name')
            ->get($columns)
            ->map(function ($user) {
                $email = strtolower(trim((string) $user->email));
                $domain = str_contains($email, '@') ? substr($email, strrpos($email, '@') + 1) : '';

                return [
                    'name' => trim((string) ($user->name ?? '')) !== '' ? (string) $user->name : $email,
                    'email' => $email,
                    'domain' => $domain,
                    'role' => (string) ($user->role ?? ''),
                    'designation' => (string) ($user->designation ?? ''),
                    'active' => (bool) $user->is_active,
                    'google' => trim((string) ($user->google_id ?? '')) !== '',
                    'gmail' => $this->openUrl($email, 'https://mail.google.com/mail/'),
                    'drive' => $this->openUrl($email, 'https://drive.google.com/'),
                    'calendar' => $this->openUrl($email, 'https://calendar.google.com/'),
                ];
            })
            ->filter(fn ($row) => $row['email'] !== '')
            ->unique('email')
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'org' => $rows->where('domain', self::DOMAIN)->count(),
                'active' => $rows->where('active', true)->count(),
                'domain' => self::DOMAIN,
            ],
        ]);
    }

    /** Google's account chooser, prefilled with this address, then the app asked for. */
    private function openUrl(string $email, string $continue): string
    {
        return 'https://accounts.google.com/AccountChooser?Email='.rawurlencode($email)
            .'&continue='.rawurlencode($continue);
    }
}
