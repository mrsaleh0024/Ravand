<?php

namespace Tests\Feature\Customers;

use App\Domain\Customers\CustomerStatus;
use App\Models\Customer;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Phase-02 customer CRM smoke checks (spec section 10, phase 02):
 * create/edit/archive, multiple phones, shared-phone duplicate warning,
 * Persian/Latin digit search, tags/notes/interactions, tenant isolation.
 */
class CustomerCrmTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_customer_creation_supports_all_social_platforms_and_province_city(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        foreach (['eitaa', 'bale', 'rubika'] as $index => $platform) {
            $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
                ->post(route('customers.store'), [
                    'display_name' => "مشتری {$platform}",
                    'social_platform' => $platform,
                    'social_handle' => "customer_{$index}",
                    'province' => 'تهران',
                    'city' => 'تهران',
                ])
                ->assertRedirect();

            $customer = Customer::query()
                ->where('display_name', "مشتری {$platform}")
                ->firstOrFail();

            $this->assertDatabaseHas('customer_social_accounts', [
                'customer_id' => $customer->id,
                'platform' => $platform,
                'username' => "customer_{$index}",
            ]);

            $this->assertDatabaseHas('customer_addresses', [
                'customer_id' => $customer->id,
                'province' => 'تهران',
                'city' => 'تهران',
            ]);
        }
    }

    public function test_customer_can_be_created_without_a_phone(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'خانم رضایی',
                'acquisition_channel' => 'instagram',
            ]);

        $response->assertRedirect();

        $customer = Customer::query()->firstOrFail();

        $this->assertSame('خانم رضایی', $customer->display_name);
        $this->assertSame('CUS-000001', $customer->customer_number);
        $this->assertSame(0, $customer->contacts()->count());
    }

    public function test_customer_number_sequence_is_per_tenant(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createTenantWithUser('فروشگاه ب', 'owner');

        $this->actingAs($ownerA)->withCurrentTenant($ownerA, $tenantA)
            ->post(route('customers.store'), ['display_name' => 'مشتری الف']);

        $this->actingAs($ownerB)->withCurrentTenant($ownerB, $tenantB)
            ->post(route('customers.store'), ['display_name' => 'مشتری ب']);

        // The global tenant scope follows the current context (tenant B),
        // so cross-tenant assertions bypass it explicitly.
        $numberA = Customer::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantA->id)->first()->customer_number;
        $numberB = Customer::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantB->id)->first()->customer_number;

        $this->assertSame('CUS-000001', $numberA);
        $this->assertSame('CUS-000001', $numberB);
    }

    public function test_a_customer_can_have_multiple_phones(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'مشتری چندشماره',
                'phone' => '۰۹۱۲۱۱۱۲۲۳۳',
            ]);

        $customer = Customer::query()->firstOrFail();

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customer-contacts.store', $customer), [
                'contact_type' => 'mobile',
                'value' => '+98 935 444 5566',
                'label' => 'work',
            ])
            ->assertRedirect();

        $this->assertSame(2, $customer->contacts()->count());
        $this->assertSame('09354445566', $customer->contacts()->orderByDesc('id')->first()->normalized_value);
    }

    public function test_shared_phone_is_a_warning_not_an_error(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        // First customer with the number.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'مشتری اول',
                'phone' => '09121112233',
            ]);

        // Second customer with the SAME number: blocked with a warning …
        $response = $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'مشتری دوم',
                'phone' => '09121112233',
            ]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('duplicates');

        // …and created only after the explicit confirmation.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'مشتری دوم',
                'phone' => '09121112233',
                'force' => '1',
            ]);

        $this->assertSame(2, Customer::query()->count());
    }

    public function test_search_matches_persian_and_latin_digits(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'مشتری شماره‌دار',
                'phone' => '09121112233',
            ]);

        // Persian-digit search term finds the Latin-digit stored value.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->get(route('customers.index', ['search' => '۰۹۱۲۱۱۱']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/Index')
                ->has('customers.data', 1));

        // Latin digits work too.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->get(route('customers.index', ['search' => '0912111']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1));

        // Normalized name search with Arabic Yeh finds the Persian one.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->get(route('customers.index', ['search' => 'مشتري شماره']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1));
    }

    public function test_tags_notes_and_interactions_can_be_recorded(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), ['display_name' => 'مشتری کامل']);

        $customer = Customer::query()->firstOrFail();

        // Tag vocabulary + attach + detach.
        $tag = Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'وفادار',
            'slug' => 'loyal',
        ]);

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customer-tags.attach', $customer), ['tag_id' => $tag->id])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_tag', [
            'customer_id' => $customer->id,
            'tag_id' => $tag->id,
        ]);

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->delete(route('customer-tags.detach', [$customer, $tag]))
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_tag', [
            'customer_id' => $customer->id,
        ]);

        // Note.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customer-notes.store', $customer), [
                'body' => 'یادداشت اول',
                'visibility' => 'team',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'body' => 'یادداشت اول',
        ]);

        // Interaction.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customer-interactions.store', $customer), [
                'interaction_type' => 'message',
                'channel' => 'instagram',
                'summary' => 'پیام درباره سفارش',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_interactions', [
            'customer_id' => $customer->id,
            'summary' => 'پیام درباره سفارش',
        ]);
    }

    public function test_customer_can_be_edited_and_archived_with_audit(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), ['display_name' => 'نام اولیه']);

        $customer = Customer::query()->firstOrFail();

        // Edit.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->put(route('customers.update', $customer), [
                'display_name' => 'نام ویرایش‌شده',
                'customer_status' => 'active',
                'acquisition_channel' => 'referral',
            ])
            ->assertRedirect();

        $this->assertSame('نام ویرایش‌شده', $customer->refresh()->display_name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer.updated',
            'entity_id' => $customer->id,
        ]);

        // Archive (owner allowed).
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->patch(route('customers.archive', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSame(CustomerStatus::Archived, $customer->refresh()->customer_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.archived']);

        // Archived customers are hidden from the default list…
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));

        // …but visible through the explicit status filter.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->get(route('customers.index', ['status' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));

        // Restore.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->patch(route('customers.restore', $customer))
            ->assertRedirect();

        $this->assertSame(CustomerStatus::Active, $customer->refresh()->customer_status);
    }

    public function test_operator_cannot_archive_but_can_create_customers(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        // Operators can create customers (data-entry role, spec 8.2).
        $this->actingAs($operator)->withCurrentTenant($operator, $tenant)
            ->post(route('customers.store'), ['display_name' => 'مشتری اپراتور'])
            ->assertRedirect();

        $customer = Customer::query()->firstOrFail();

        // …but archiving is an owner/manager decision.
        $this->actingAs($operator)->withCurrentTenant($operator, $tenant)
            ->patch(route('customers.archive', $customer))
            ->assertForbidden();
    }

    public function test_private_note_is_hidden_from_operators(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), ['display_name' => 'مشتری محرمانه']);

        $customer = Customer::query()->firstOrFail();

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customer-notes.store', $customer), [
                'body' => 'یادداشت خصوصی مدیر',
                'visibility' => 'private',
            ]);

        // Owner sees the private note…
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->has('notes', 1)
                ->where('notes.0.body', 'یادداشت خصوصی مدیر'));

        // …the operator does not.
        $this->actingAs($operator)->withCurrentTenant($operator, $tenant)
            ->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page->has('notes', 0));
    }

    public function test_another_tenant_cannot_access_customer_by_id(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createTenantWithUser('فروشگاه ب', 'owner');

        $this->actingAs($ownerA)->withCurrentTenant($ownerA, $tenantA)
            ->post(route('customers.store'), ['display_name' => 'مشتری فروشگاه الف']);

        $customerA = Customer::query()->where('tenant_id', $tenantA->id)->firstOrFail();

        // Tenant B's owner must not open tenant A's customer (spec 2.2).
        $this->actingAs($ownerB)->withCurrentTenant($ownerB, $tenantB)
            ->get(route('customers.show', $customerA))
            ->assertNotFound();

        $this->actingAs($ownerB)->withCurrentTenant($ownerB, $tenantB)
            ->put(route('customers.update', $customerA), [
                'display_name' => 'هک',
                'customer_status' => 'active',
                'acquisition_channel' => 'other',
            ])
            ->assertNotFound();

        // The tenant-scoped list never leaks the other tenant's rows.
        $this->actingAs($ownerB)->withCurrentTenant($ownerB, $tenantB)
            ->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
    }

    public function test_consent_defaults_are_explicit(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تست');

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customers.store'), [
                'display_name' => 'مشتری بدون رضایت',
                'phone' => '09121112233',
            ]);

        // Recording a phone number creates NO consent rows (spec 8.5).
        $customer = Customer::query()->firstOrFail();
        $this->assertSame(0, $customer->consents()->count());

        // Explicit marketing denial is recorded and audited.
        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)
            ->post(route('customer-consents.store', $customer), [
                'channel' => 'sms',
                'purpose' => 'marketing',
                'status' => 'denied',
            ])
            ->assertRedirect();

        $this->assertSame(1, $customer->consents()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.consent_recorded']);
    }
}
