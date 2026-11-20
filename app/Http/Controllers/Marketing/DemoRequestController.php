<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\DemoRequestKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemoRequestRequest;
use App\Models\DemoRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class DemoRequestController extends Controller
{
    public function store(StoreDemoRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $source = $data['source'];

        // Bots fill the hidden "website" field; pretend it worked and drop it.
        if (filled($request->input('website'))) {
            return $this->sent($source);
        }

        [$email, $phone] = $this->splitContact($data['contact'] ?? null);

        DemoRequest::query()->create([
            'kind' => $data['kind'] ?? DemoRequestKind::Demo->value,
            'source' => $source,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? $email,
            'phone' => $phone,
            'school_name' => $data['school_name'] ?? null,
            'student_count' => $data['student_count'] ?? null,
            'message' => $data['message'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return $this->sent($source);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitContact(?string $contact): array
    {
        if (blank($contact)) {
            return [null, null];
        }

        return str_contains($contact, '@')
            ? [Str::lower(trim($contact)), null]
            : [null, User::normalisePhone($contact) ?? trim($contact)];
    }

    private function sent(string $source): RedirectResponse
    {
        if ($source === 'contact') {
            return redirect()->route('contact')->with('demo_sent', $source);
        }

        return redirect()->to(route('home').'#'.$source)->with('demo_sent', $source);
    }
}
