<?php

namespace App\Http\Controllers;

use App\Enums\ContactTopic;
use App\Enums\ElectorateKind;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\Electorate;
use App\Models\Policy;
use App\Notifications\ContactMessageReceived;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * The contact form. Unlike the public pages, it uses a session, for CSRF
 * protection and to show validation errors, so it is in routes/web.php.
 */
class ContactController extends Controller
{
    public function create(Request $request): View
    {
        return view('contact', [
            'topics' => ContactTopic::cases(),
            'selectedTopic' => ContactTopic::tryFrom((string) $request->query('topic')) ?? ContactTopic::General,
            'subject' => $this->subject($request->query('policy'), $request->query('district')),
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        if ($request->isFromBot()) {
            return redirect()->route('contact')->with('sent', $request->string('email')->toString());
        }

        $subject = $this->subject($request->input('policy'), $request->input('district'));

        $message = ContactMessage::query()->create([
            'topic' => $request->enum('topic', ContactTopic::class),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'context' => $subject['label'] ?? null,
            'context_url' => $subject['url'] ?? null,
            'message' => $request->input('message'),
        ]);

        // The message is already saved, so a mail failure is logged and
        // never loses it; it still appears in the admin panel.
        if (filled(config('site.contact_email'))) {
            rescue(fn () => Notification::route('mail', config('site.contact_email'))->notify(new ContactMessageReceived($message)));
        }

        return redirect()->route('contact')
            ->with('sent', $message->email)
            ->with('back', $message->context_url);
    }

    /**
     * The published question or district a message is about, if the visitor
     * came from one. Unknown or unpublished slugs are ignored.
     *
     * @return array{field: string, slug: string, label: string, heading: string, text: string, url: string}|null
     */
    private function subject(mixed $policySlug, mixed $districtSlug): ?array
    {
        if (is_string($policySlug) && $policy = Policy::query()->published()->where('slug', $policySlug)->first()) {
            return [
                'field' => 'policy',
                'slug' => $policy->slug,
                'label' => "Question: {$policy->question}",
                'heading' => 'About this question',
                'text' => $policy->question,
                'url' => route('policies.show', $policy->slug),
            ];
        }

        if (is_string($districtSlug) && $district = Electorate::query()->where('kind', ElectorateKind::District)->where('slug', $districtSlug)->first()) {
            return [
                'field' => 'district',
                'slug' => $district->slug,
                'label' => "District: {$district->name}",
                'heading' => 'About this district',
                'text' => $district->name,
                'url' => route('districts.show', $district->slug),
            ];
        }

        return null;
    }
}
