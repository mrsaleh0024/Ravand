<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\User;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tenant tag vocabulary (spec 5.2). Creating tags is an owner/manager
 * ability; every member can apply existing tags to customers.
 */
class TagController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility('tags.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:40'],
        ]);

        $tenant = $this->context->tenant();
        $name = PersianText::normalize($validated['name']);
        $slug = Str::slug(PersianText::normalize($validated['name']), '-', 'en') ?: substr(md5($name), 0, 10);

        $exists = Tag::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) use ($slug, $name): void {
                $q->where('slug', $slug)->orWhere('name', $name);
            })
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => __('customers.tag_exists')]);
        }

        Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'color_token' => $validated['color'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('customers.tag_created')]);
    }

    private function authorizeAbility(string $ability): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor !== null && $actor->can($ability), 403);
    }
}
